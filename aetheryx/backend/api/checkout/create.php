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
$role = getAuthenticatedRole();
$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request.']);
    exit;
}

$paymentMethod = (string)($data['payment_method'] ?? '');
$checkoutToken = strtolower(trim((string)($data['checkout_token'] ?? '')));
$demoOutcome = (string)($data['demo_outcome'] ?? 'success');
$directEquipmentId = filter_var($data['equipment_id'] ?? null, FILTER_VALIDATE_INT);
$directQuantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);
$hasDirectEquipment = array_key_exists('equipment_id', $data);
$delivery = is_array($data['delivery'] ?? null) ? $data['delivery'] : [];
$deliveryFields = [
    'recipient_name' => 100,
    'phone' => 30,
    'address_line' => 255,
    'city' => 100,
    'region' => 100,
    'postal_code' => 20,
];
foreach ($deliveryFields as $field => $maximumLength) {
    $value = $delivery[$field] ?? null;
    if (!is_string($value) && !is_numeric($value)) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Enter valid delivery contact and address details.']);
        exit;
    }
    $delivery[$field] = trim((string)$value);
    if ($delivery[$field] === '' || strlen($delivery[$field]) > $maximumLength) {
        http_response_code(422);
        echo json_encode(['success' => false, 'message' => 'Enter valid delivery contact and address details.']);
        exit;
    }
}
$phoneDigits = preg_replace('/\D/', '', $delivery['phone']);
if (!preg_match('/^[0-9+\-\s().]+$/', $delivery['phone']) || strlen((string)$phoneDigits) < 7) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Enter a valid phone number for delivery.']);
    exit;
}
$notes = $delivery['delivery_notes'] ?? '';
if (!is_string($notes)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Delivery instructions must be text.']);
    exit;
}
$delivery['delivery_notes'] = trim($notes);
if (strlen($delivery['delivery_notes']) > 500) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Delivery instructions must be 500 characters or fewer.']);
    exit;
}

if (!in_array($paymentMethod, ['cash_on_delivery', 'demo_payment'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a supported payment method.']);
    exit;
}

if ($hasDirectEquipment && (
    $directEquipmentId === false || $directEquipmentId < 1 ||
    $directQuantity === false || $directQuantity < 1 || $directQuantity > 999
)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a valid product quantity.']);
    exit;
}

if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $checkoutToken)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid checkout token is required.']);
    exit;
}

if ($paymentMethod === 'demo_payment' && !in_array($demoOutcome, ['success', 'failure'], true)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Invalid demonstration payment outcome.']);
    exit;
}

try {
    $pdo->beginTransaction();

    $existingStmt = $pdo->prepare(
        'SELECT id, order_number, order_status, payment_status, payment_method, total_amount
         FROM orders
         WHERE user_id = :user_id AND checkout_token = :checkout_token
         LIMIT 1'
    );
    $existingStmt->execute([
        ':user_id' => $userId,
        ':checkout_token' => $checkoutToken,
    ]);
    $existingOrder = $existingStmt->fetch();

    if ($existingOrder) {
        $pdo->commit();
        $replaySuccess = in_array($existingOrder['payment_status'], ['pending', 'paid'], true);
        http_response_code($replaySuccess ? 200 : ($existingOrder['payment_status'] === 'failed' ? 402 : 409));
        echo json_encode([
            'success' => $replaySuccess,
            'message' => $replaySuccess
                ? 'This checkout was already processed.'
                : ($existingOrder['payment_status'] === 'failed'
                    ? 'The demonstration payment failed. No stock was assigned.'
                    : 'This checkout has already been closed.'),
            'order' => [
                'id' => (int)$existingOrder['id'],
                'order_number' => $existingOrder['order_number'],
                'order_status' => $existingOrder['order_status'],
                'payment_status' => $existingOrder['payment_status'],
                'payment_method' => $existingOrder['payment_method'],
                'total_amount' => (float)$existingOrder['total_amount'],
                'simulated' => $existingOrder['payment_method'] === 'demo_payment',
            ],
        ]);
        exit;
    }

    if ($hasDirectEquipment) {
        $itemsStmt = $pdo->prepare(
            'SELECT e.id AS equipment_id, :quantity AS quantity, e.name, e.price,
                    e.access_role, e.is_black_market, e.available_quantity, e.status
             FROM equipment e
             WHERE e.id = :equipment_id
             FOR UPDATE'
        );
        $itemsStmt->execute([
            ':quantity' => $directQuantity,
            ':equipment_id' => $directEquipmentId,
        ]);
    } else {
        $itemsStmt = $pdo->prepare(
            'SELECT ci.id AS cart_id, ci.equipment_id, ci.quantity, e.name, e.price,
                    e.access_role, e.is_black_market, e.available_quantity, e.status
             FROM cart_items ci
             INNER JOIN equipment e ON e.id = ci.equipment_id
             WHERE ci.user_id = :user_id
             ORDER BY e.id
             FOR UPDATE'
        );
        $itemsStmt->execute([':user_id' => $userId]);
    }
    $items = $itemsStmt->fetchAll();

    if (!$items) {
        if ($hasDirectEquipment) {
            throw new DomainException('The selected product is no longer available.');
        }
        $duplicateStmt = $pdo->prepare(
            'SELECT id, order_number, order_status, payment_status, payment_method, total_amount
             FROM orders
             WHERE user_id = :user_id AND checkout_token = :checkout_token
             LIMIT 1 FOR UPDATE'
        );
        $duplicateStmt->execute([
            ':user_id' => $userId,
            ':checkout_token' => $checkoutToken,
        ]);
        $duplicate = $duplicateStmt->fetch();
        if ($duplicate) {
            $pdo->commit();
            $replaySuccess = in_array($duplicate['payment_status'], ['pending', 'paid'], true);
            http_response_code($replaySuccess ? 200 : ($duplicate['payment_status'] === 'failed' ? 402 : 409));
            echo json_encode([
                'success' => $replaySuccess,
                'message' => $replaySuccess
                    ? 'This checkout was already processed.'
                    : ($duplicate['payment_status'] === 'failed'
                        ? 'The demonstration payment failed. No stock was assigned.'
                        : 'This checkout has already been closed.'),
                'order' => [
                    'id' => (int)$duplicate['id'],
                    'order_number' => $duplicate['order_number'],
                    'order_status' => $duplicate['order_status'],
                    'payment_status' => $duplicate['payment_status'],
                    'payment_method' => $duplicate['payment_method'],
                    'total_amount' => (float)$duplicate['total_amount'],
                    'simulated' => $duplicate['payment_method'] === 'demo_payment',
                ],
            ]);
            exit;
        }

        $pdo->rollBack();
        http_response_code(409);
        echo json_encode(['success' => false, 'message' => 'Your cart is empty.']);
        exit;
    }

    $totalCents = 0;
    foreach ($items as $item) {
        $accessRole = (string)$item['access_role'];
        $isBlackMarket = (bool) $item['is_black_market'];
        $canAccess = $isBlackMarket
            ? $role === 'enemy'
            : ($accessRole === 'common' || $accessRole === $role);
        $quantity = (int)$item['quantity'];
        $priceCents = (int)round((float)$item['price'] * 100);

        if (!$canAccess) {
            throw new DomainException($isBlackMarket
                ? 'Only Villain accounts can purchase Black Market equipment.'
                : ($hasDirectEquipment
                    ? 'This product is restricted to another role.'
                    : 'Your cart contains equipment restricted to another role.'));
        }
        if ($item['status'] !== 'available' || $quantity < 1 || $quantity > (int)$item['available_quantity']) {
            throw new DomainException('Stock changed for ' . $item['name'] . '. Review your order and try again.');
        }
        if ($priceCents < 1) {
            throw new DomainException('A price has not been configured for ' . $item['name'] . '.');
        }
        $totalCents += $priceCents * $quantity;
    }

    if ($totalCents > 999999999) {
        throw new DomainException('The order total exceeds the supported amount.');
    }

    $paymentFailed = $paymentMethod === 'demo_payment' && $demoOutcome === 'failure';
    $paymentStatus = $paymentFailed
        ? 'failed'
        : ($paymentMethod === 'demo_payment' ? 'paid' : 'pending');
    $orderStatus = $paymentFailed
        ? 'failed'
        : ($paymentMethod === 'demo_payment' ? 'processing' : 'pending');
    $total = number_format($totalCents / 100, 2, '.', '');
    $orderNumber = 'AX-' . date('ymdHis') . '-' . strtoupper(bin2hex(random_bytes(3)));

    $orderStmt = $pdo->prepare(
        'INSERT INTO orders
            (user_id, order_number, checkout_token, order_status, payment_method, payment_status, total_amount)
         VALUES
            (:user_id, :order_number, :checkout_token, :order_status, :payment_method, :payment_status, :total_amount)'
    );
    $orderStmt->execute([
        ':user_id' => $userId,
        ':order_number' => $orderNumber,
        ':checkout_token' => $checkoutToken,
        ':order_status' => $orderStatus,
        ':payment_method' => $paymentMethod,
        ':payment_status' => $paymentStatus,
        ':total_amount' => $total,
    ]);
    $orderId = (int)$pdo->lastInsertId();

    $deliveryStmt = $pdo->prepare(
        'INSERT INTO order_delivery_details
            (order_id, recipient_name, phone, address_line, city, region, postal_code, delivery_notes)
         VALUES
            (:order_id, :recipient_name, :phone, :address_line, :city, :region, :postal_code, :delivery_notes)'
    );
    $deliveryStmt->execute([
        ':order_id' => $orderId,
        ':recipient_name' => $delivery['recipient_name'],
        ':phone' => $delivery['phone'],
        ':address_line' => $delivery['address_line'],
        ':city' => $delivery['city'],
        ':region' => $delivery['region'],
        ':postal_code' => $delivery['postal_code'],
        ':delivery_notes' => $delivery['delivery_notes'] !== '' ? $delivery['delivery_notes'] : null,
    ]);

    $lineStmt = $pdo->prepare(
        'INSERT INTO order_items (order_id, equipment_id, equipment_name, quantity, unit_price, line_total)
         VALUES (:order_id, :equipment_id, :equipment_name, :quantity, :unit_price, :line_total)'
    );
    foreach ($items as $item) {
        $quantity = (int)$item['quantity'];
        $unitPrice = number_format((float)$item['price'], 2, '.', '');
        $lineTotal = number_format(((int)round((float)$item['price'] * 100) * $quantity) / 100, 2, '.', '');
        $lineStmt->execute([
            ':order_id' => $orderId,
            ':equipment_id' => (int)$item['equipment_id'],
            ':equipment_name' => $item['name'],
            ':quantity' => $quantity,
            ':unit_price' => $unitPrice,
            ':line_total' => $lineTotal,
        ]);
    }

    $transactionReference = ($paymentMethod === 'demo_payment' ? 'DEMO-' : 'COD-')
        . strtoupper(bin2hex(random_bytes(8)));
    $transactionStmt = $pdo->prepare(
        'INSERT INTO payment_transactions
            (order_id, transaction_reference, payment_method, amount, payment_status, is_simulated)
         VALUES
            (:order_id, :reference, :payment_method, :amount, :payment_status, :is_simulated)'
    );
    $transactionStmt->execute([
        ':order_id' => $orderId,
        ':reference' => $transactionReference,
        ':payment_method' => $paymentMethod,
        ':amount' => $total,
        ':payment_status' => $paymentStatus,
        ':is_simulated' => $paymentMethod === 'demo_payment' ? 1 : 0,
    ]);

    if (!$paymentFailed) {
        $stockStmt = $pdo->prepare(
            'UPDATE equipment SET available_quantity = available_quantity - :decrement
             WHERE id = :equipment_id AND available_quantity >= :required_quantity'
        );
        $inventoryStmt = $pdo->prepare(
            'INSERT INTO inventory (user_id, equipment_id, quantity, status, purchase_price, notes)
             VALUES (:user_id, :equipment_id, :quantity, "assigned", :purchase_price, :notes)'
        );
        foreach ($items as $item) {
            $quantity = (int)$item['quantity'];
            $stockStmt->execute([
                ':decrement' => $quantity,
                ':equipment_id' => (int)$item['equipment_id'],
                ':required_quantity' => $quantity,
            ]);
            if ($stockStmt->rowCount() !== 1) {
                throw new DomainException('Stock changed during checkout. Please review your order.');
            }
            if ($paymentStatus === 'paid') {
                $inventoryStmt->execute([
                    ':user_id' => $userId,
                    ':equipment_id' => (int)$item['equipment_id'],
                    ':quantity' => $quantity,
                    ':purchase_price' => number_format((float)$item['price'], 2, '.', ''),
                    ':notes' => 'Order ' . $orderNumber,
                ]);
            }
        }

        if (!$hasDirectEquipment) {
            $deleteCart = $pdo->prepare('DELETE FROM cart_items WHERE user_id = :user_id');
            $deleteCart->execute([':user_id' => $userId]);
        }
    }

    $pdo->commit();

    if ($paymentFailed) {
        $message = sprintf('Payment failed for order %s. No stock was changed.', $orderNumber);
        createNotification($pdo, (int) $userId, 'Payment failed', $message, 'warning');
        notifyAdminsOfActivity($pdo, (int) $userId, 'Checkout payment failed', $message, 'warning');
        http_response_code(402);
    } else {
        $message = sprintf('Order %s was placed for PHP %s.', $orderNumber, $total);
        createNotification($pdo, (int) $userId, 'Order placed', $message, 'success');
        notifyAdminsOfActivity($pdo, (int) $userId, 'New order placed', $message, 'info');
    }
    echo json_encode([
        'success' => !$paymentFailed,
        'message' => $paymentFailed
            ? 'The demonstration payment failed. No stock was changed.'
            : ($paymentMethod === 'demo_payment'
                ? 'Demonstration payment confirmed. No real money was charged.'
                : 'Order placed. Payment is due on delivery.'),
        'order' => [
            'id' => $orderId,
            'order_number' => $orderNumber,
            'order_status' => $orderStatus,
            'payment_status' => $paymentStatus,
            'payment_method' => $paymentMethod,
            'total_amount' => (float)$total,
            'transaction_reference' => $transactionReference,
            'simulated' => $paymentMethod === 'demo_payment',
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
    error_log('ÆTHERYX checkout failed: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Checkout could not be completed. No payment or stock change was confirmed.',
        'code' => 'CHECKOUT_FAILED',
    ]);
}
