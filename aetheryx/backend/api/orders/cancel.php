<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
startSecureSession();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/role-check.php';
require_once __DIR__ . '/../../helpers/notify.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireMarketplaceRole();
$userId = getAuthenticatedUserId();
$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request.']);
    exit;
}

$orderId = filter_var($data['order_id'] ?? null, FILTER_VALIDATE_INT);
$reason = $data['reason'] ?? null;
if (!$orderId || $orderId < 1 || !is_string($reason)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid order and cancellation reason are required.']);
    exit;
}
$reason = trim($reason);
$reasonLength = function_exists('mb_strlen') ? mb_strlen($reason, 'UTF-8') : strlen($reason);
if ($reason === '' || $reasonLength > 500) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a cancellation reason (up to 500 characters).']);
    exit;
}

$cancellableStatuses = ['pending', 'confirmed', 'processing', 'ready_for_delivery'];

try {
    $pdo->beginTransaction();
    $orderStmt = $pdo->prepare(
        'SELECT id, order_number, order_status, payment_status
         FROM orders
         WHERE id = :order_id AND user_id = :user_id
         FOR UPDATE'
    );
    $orderStmt->execute([
        ':order_id' => $orderId,
        ':user_id' => $userId,
    ]);
    $order = $orderStmt->fetch();

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

    if (!in_array($order['order_status'], $cancellableStatuses, true)) {
        throw new DomainException('This order can no longer be cancelled.');
    }

    if ($order['payment_status'] === 'pending') {
        $itemsStmt = $pdo->prepare(
            'SELECT equipment_id, quantity
             FROM order_items
             WHERE order_id = :order_id AND equipment_id IS NOT NULL AND resale_listing_id IS NULL'
        );
        $itemsStmt->execute([':order_id' => $orderId]);
        $restoreStock = $pdo->prepare(
            'UPDATE equipment
             SET available_quantity = available_quantity + :quantity
             WHERE id = :equipment_id'
        );
        foreach ($itemsStmt->fetchAll() as $item) {
            $restoreStock->execute([
                ':quantity' => (int)$item['quantity'],
                ':equipment_id' => (int)$item['equipment_id'],
            ]);
            if ($restoreStock->rowCount() !== 1) {
                throw new DomainException('Order stock could not be restored safely. Please contact support.');
            }
        }
        $paymentStatus = 'cancelled';
    } elseif ($order['payment_status'] === 'paid') {
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
            ':user_id' => $userId,
            ':order_note' => 'Order ' . $order['order_number'],
            ':order_id' => $orderId,
        ]);
        $orderItems = $itemsStmt->fetchAll();
        if (!$orderItems) {
            throw new DomainException('Assigned order inventory could not be verified. Please contact support.');
        }

        $restoreStock = $pdo->prepare(
            'UPDATE equipment
             SET available_quantity = available_quantity + :quantity
             WHERE id = :equipment_id'
        );
        $returnInventory = $pdo->prepare(
            'UPDATE inventory
             SET status = "returned", returned_at = CURRENT_TIMESTAMP
             WHERE id = :inventory_id AND status = "assigned"'
        );
        foreach ($orderItems as $item) {
            if (!$item['inventory_id'] || (int)$item['inventory_quantity'] !== (int)$item['quantity']) {
                throw new DomainException('Assigned order inventory changed. Please contact support.');
            }
            $restoreStock->execute([
                ':quantity' => (int)$item['quantity'],
                ':equipment_id' => (int)$item['equipment_id'],
            ]);
            $returnInventory->execute([':inventory_id' => (int)$item['inventory_id']]);
            if ($restoreStock->rowCount() !== 1 || $returnInventory->rowCount() !== 1) {
                throw new DomainException('Order inventory could not be updated safely. Please contact support.');
            }
        }
        // Cancellation stops fulfillment; paid transactions are not silently refunded.
        $paymentStatus = 'paid';
    } else {
        throw new DomainException('This order payment state does not allow cancellation.');
    }

    $updateStmt = $pdo->prepare(
        'UPDATE orders
         SET order_status = "cancelled",
             payment_status = :payment_status,
             cancellation_reason = :reason,
             cancelled_at = CURRENT_TIMESTAMP
         WHERE id = :order_id'
    );
    $updateStmt->execute([
        ':payment_status' => $paymentStatus,
        ':reason' => $reason,
        ':order_id' => $orderId,
    ]);

    if ($paymentStatus === 'cancelled') {
        $pdo->prepare(
            'UPDATE payment_transactions SET payment_status = "cancelled"
             WHERE order_id = :order_id AND payment_status = "pending"'
        )->execute([':order_id' => $orderId]);
    }

    $pdo->prepare("UPDATE resale_listings SET status='active',reservation_order_id=NULL WHERE reservation_order_id=:order AND status='reserved'")->execute([':order'=>$orderId]);

    $pdo->commit();
    $message = sprintf('Order %s was cancelled.', $order['order_number']);
    createNotification($pdo, (int) $userId, 'Order cancelled', $message, 'warning');
    notifyAdminsOfActivity($pdo, (int) $userId, 'Order cancelled', $message, 'warning');
    foreach ($sellerIds as $sellerId) {
        createNotification(
            $pdo,
            (int) $sellerId,
            'Resale listing available again',
            sprintf('Order %s was cancelled and your resale listing is available again.', $order['order_number']),
            'info'
        );
    }
    echo json_encode([
        'success' => true,
        'message' => 'Order cancelled successfully.',
        'order' => [
            'id' => (int)$order['id'],
            'order_status' => 'cancelled',
            'payment_status' => $paymentStatus,
            'cancellation_reason' => $reason,
            'cancelled_at' => date('Y-m-d H:i:s'),
        ],
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
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
    error_log('ÆTHERYX buyer order cancellation failed: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to cancel this order. Please try again.']);
}
