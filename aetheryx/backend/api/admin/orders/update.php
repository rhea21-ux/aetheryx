<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/session.php';
startSecureSession();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/role-check.php';
require_once __DIR__ . '/../../../helpers/notify.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAdminRole();
$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request.']);
    exit;
}

$orderId = filter_var($data['order_id'] ?? null, FILTER_VALIDATE_INT);
$action = (string)($data['action'] ?? '');
if (!$orderId || $orderId < 1 || !in_array($action, ['mark_paid', 'complete', 'cancel', 'refund_demo'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid order and action are required.']);
    exit;
}

try {
    $pdo->beginTransaction();
    $stmt = $pdo->prepare(
        'SELECT id, user_id, order_number, order_status, payment_method, payment_status
         FROM orders WHERE id = :order_id FOR UPDATE'
    );
    $stmt->execute([':order_id' => $orderId]);
    $order = $stmt->fetch();
    if (!$order) {
        $pdo->rollBack();
        http_response_code(404);
        echo json_encode(['success' => false, 'message' => 'Order not found.']);
        exit;
    }
    $sellerStmt = $pdo->prepare(
        'SELECT DISTINCT seller_id FROM order_items
         WHERE order_id = :order_id AND seller_id IS NOT NULL'
    );
    $sellerStmt->execute([':order_id' => $orderId]);
    $sellerIds = $sellerStmt->fetchAll(PDO::FETCH_COLUMN);

    if ($action === 'mark_paid') {
        if ($order['payment_method'] !== 'cash_on_delivery' || $order['payment_status'] !== 'pending') {
            throw new DomainException('Only pending cash-on-delivery orders can be marked paid.');
        }
        $itemsStmt = $pdo->prepare(
            'SELECT equipment_id, equipment_name, quantity, unit_price, resale_listing_id, seller_id, inventory_id
             FROM order_items WHERE order_id = :order_id'
        );
        $itemsStmt->execute([':order_id' => $orderId]);
        $assignInventory = $pdo->prepare(
            'INSERT INTO inventory (user_id, equipment_id, quantity, status, purchase_price, notes)
             VALUES (:user_id, :equipment_id, :quantity, "assigned", :purchase_price, :notes)'
        );
        foreach ($itemsStmt->fetchAll() as $item) {
            if (!empty($item['resale_listing_id'])) {
                $listingStmt = $pdo->prepare("SELECT id,inventory_id,seller_id FROM resale_listings WHERE id=:listing AND status='reserved' AND reservation_order_id=:order FOR UPDATE");
                $listingStmt->execute([':listing'=>(int)$item['resale_listing_id'], ':order'=>$orderId]);
                $listing = $listingStmt->fetch(PDO::FETCH_ASSOC);
                if (!$listing || (int)$listing['inventory_id'] !== (int)$item['inventory_id'] || (int)$listing['seller_id'] !== (int)$item['seller_id']) {
                    throw new DomainException('Reserved resale ownership could not be verified.');
                }
                $sellerUpdate = $pdo->prepare("UPDATE inventory SET status='sold',sale_price=:price,sold_at=CURRENT_TIMESTAMP WHERE id=:inventory AND user_id=:seller AND status='assigned'");
                $sellerUpdate->execute([':price'=>$item['unit_price'], ':inventory'=>$listing['inventory_id'], ':seller'=>$listing['seller_id']]);
                if ($sellerUpdate->rowCount() !== 1) throw new DomainException('Seller inventory changed before COD payment was recorded.');
                $pdo->prepare("UPDATE resale_listings SET status='sold',sold_at=CURRENT_TIMESTAMP WHERE id=:id AND status='reserved' AND reservation_order_id=:order")->execute([':id'=>$listing['id'], ':order'=>$orderId]);
                $pdo->prepare('INSERT INTO inventory (user_id,equipment_id,quantity,status,purchase_price,assigned_at,notes) VALUES (:user,:equipment,1,"assigned",:price,CURRENT_TIMESTAMP,:notes)')->execute([':user'=>(int)$order['user_id'], ':equipment'=>(int)$item['equipment_id'], ':price'=>$item['unit_price'], ':notes'=>'Resale order '.$order['order_number']]);
                continue;
            }
            $assignInventory->execute([
                ':user_id' => (int)$order['user_id'],
                ':equipment_id' => (int)$item['equipment_id'],
                ':quantity' => (int)$item['quantity'],
                ':purchase_price' => $item['unit_price'],
                ':notes' => 'Order ' . $order['order_number'],
            ]);
        }
        $pdo->prepare(
            'UPDATE orders SET payment_status = "paid", order_status = "processing" WHERE id = :order_id'
        )->execute([':order_id' => $orderId]);
        $pdo->prepare(
            'UPDATE payment_transactions SET payment_status = "paid"
             WHERE order_id = :order_id AND payment_status = "pending"'
        )->execute([':order_id' => $orderId]);
    } elseif ($action === 'complete') {
        if ($order['payment_status'] !== 'paid' || $order['order_status'] !== 'processing') {
            throw new DomainException('Only paid, processing orders can be completed.');
        }
        $pdo->prepare(
            'UPDATE orders SET order_status = "completed" WHERE id = :order_id'
        )->execute([':order_id' => $orderId]);
    } elseif ($action === 'cancel') {
        if ($order['payment_method'] !== 'cash_on_delivery'
            || $order['payment_status'] !== 'pending'
            || $order['order_status'] !== 'pending') {
            throw new DomainException('Only pending cash-on-delivery orders can be cancelled.');
        }

        $itemsStmt = $pdo->prepare(
            'SELECT equipment_id, quantity FROM order_items
             WHERE order_id = :order_id AND equipment_id IS NOT NULL AND resale_listing_id IS NULL'
        );
        $itemsStmt->execute([':order_id' => $orderId]);
        $restoreStock = $pdo->prepare(
            'UPDATE equipment SET available_quantity = available_quantity + :quantity
             WHERE id = :equipment_id'
        );
        foreach ($itemsStmt->fetchAll() as $item) {
            $restoreStock->execute([
                ':quantity' => (int)$item['quantity'],
                ':equipment_id' => (int)$item['equipment_id'],
            ]);
        }
        $pdo->prepare("UPDATE resale_listings SET status='active',reservation_order_id=NULL WHERE reservation_order_id=:order AND status='reserved'")->execute([':order'=>$orderId]);
        $pdo->prepare(
            'UPDATE orders
             SET order_status = "cancelled", payment_status = "cancelled",
                 cancellation_reason = "Cancelled by administrator.", cancelled_at = CURRENT_TIMESTAMP
             WHERE id = :order_id'
        )->execute([':order_id' => $orderId]);
        $pdo->prepare(
            'UPDATE payment_transactions SET payment_status = "cancelled"
             WHERE order_id = :order_id AND payment_status = "pending"'
        )->execute([':order_id' => $orderId]);
    } else {
        if ($order['payment_method'] !== 'demo_payment'
            || $order['payment_status'] !== 'paid'
            || !in_array($order['order_status'], ['processing', 'completed'], true)) {
            throw new DomainException('Only paid demonstration orders can be refunded in this system.');
        }

        $itemsStmt = $pdo->prepare(
            'SELECT oi.equipment_id, oi.quantity, i.id AS inventory_id, i.quantity AS inventory_quantity
             FROM order_items oi
             LEFT JOIN inventory i
               ON i.user_id = :user_id
              AND i.equipment_id = oi.equipment_id
              AND i.notes = :order_note
              AND i.status = "assigned"
             WHERE oi.order_id = :order_id
             FOR UPDATE'
        );
        $itemsStmt->execute([
            ':user_id' => (int)$order['user_id'],
            ':order_note' => 'Order ' . $order['order_number'],
            ':order_id' => $orderId,
        ]);
        $refundItems = $itemsStmt->fetchAll();
        if (!$refundItems || count($refundItems) === 0) {
            throw new DomainException('The order has no refundable assigned inventory.');
        }

        $restoreStock = $pdo->prepare(
            'UPDATE equipment SET available_quantity = available_quantity + :quantity
             WHERE id = :equipment_id'
        );
        $returnInventory = $pdo->prepare(
            'UPDATE inventory SET status = "returned", returned_at = CURRENT_TIMESTAMP
             WHERE id = :inventory_id AND status = "assigned"'
        );
        foreach ($refundItems as $item) {
            if (!$item['inventory_id'] || (int)$item['inventory_quantity'] !== (int)$item['quantity']) {
                throw new DomainException('All order items must still be assigned before a demo refund can be recorded.');
            }
            $restoreStock->execute([
                ':quantity' => (int)$item['quantity'],
                ':equipment_id' => (int)$item['equipment_id'],
            ]);
            $returnInventory->execute([':inventory_id' => (int)$item['inventory_id']]);
            if ($returnInventory->rowCount() !== 1) {
                throw new DomainException('An order item changed during the refund. No changes were saved.');
            }
        }

        $pdo->prepare(
            'UPDATE orders SET payment_status = "refunded", order_status = "refunded" WHERE id = :order_id'
        )->execute([':order_id' => $orderId]);
        $pdo->prepare(
            'UPDATE payment_transactions SET payment_status = "refunded"
             WHERE order_id = :order_id AND payment_status = "paid"'
        )->execute([':order_id' => $orderId]);
    }

    $pdo->commit();
    $adminId = (int) getAuthenticatedUserId();
    $actionLabels = [
        'mark_paid' => ['Payment confirmed', 'Your order payment was confirmed and fulfillment is underway.', 'success'],
        'complete' => ['Order completed', 'Your order was marked complete.', 'success'],
        'cancel' => ['Order cancelled', 'Your order was cancelled by an administrator.', 'warning'],
        'refund_demo' => ['Order refunded', 'Your demonstration payment was refunded and assigned equipment returned.', 'warning'],
    ];
    [$notificationTitle, $buyerMessage, $notificationType] = $actionLabels[$action];
    $adminMessage = sprintf('Order %s: %s.', $order['order_number'], strtolower($notificationTitle));
    createNotification($pdo, (int) $order['user_id'], $notificationTitle, $buyerMessage, $notificationType);
    createNotification($pdo, $adminId, $notificationTitle, $adminMessage, 'success');
    notifyAdminsOfActivity($pdo, $adminId, $notificationTitle, $adminMessage, 'info');
    foreach ($sellerIds as $sellerId) {
        createNotification(
            $pdo,
            (int) $sellerId,
            $notificationTitle,
            sprintf('Order %s: %s.', $order['order_number'], $buyerMessage),
            $notificationType
        );
    }
    echo json_encode([
        'success' => true,
        'message' => $action === 'refund_demo'
            ? 'Simulated payment refunded and assigned inventory returned. No real money was transferred.'
            : 'Order updated.',
    ]);
} catch (DomainException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    http_response_code(409);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ÆTHERYX admin order update failed: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update the order.']);
}
