<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/session.php';
startSecureSession();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/role-check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAdminRole();

try {
    $stmt = $pdo->query(
        'SELECT o.id, o.order_number, o.order_status, o.payment_method, o.payment_status,
                o.cancellation_reason, o.cancelled_at, o.total_amount, o.created_at,
                u.full_name, u.handle, u.email, u.role,
                d.recipient_name, d.phone, d.address_line, d.city, d.region, d.postal_code, d.delivery_notes
         FROM orders o
         INNER JOIN users u ON u.id = o.user_id
         LEFT JOIN order_delivery_details d ON d.order_id = o.id
         ORDER BY o.created_at DESC, o.id DESC
         LIMIT 250'
    );
    $orders = $stmt->fetchAll();
    $itemStmt = $pdo->prepare(
        'SELECT equipment_name, quantity, unit_price, line_total
         FROM order_items WHERE order_id = :order_id ORDER BY id'
    );
    $paymentStmt = $pdo->prepare(
        'SELECT transaction_reference, payment_status, is_simulated
         FROM payment_transactions WHERE order_id = :order_id ORDER BY id DESC LIMIT 1'
    );

    foreach ($orders as &$order) {
        $orderId = (int)$order['id'];
        $itemStmt->execute([':order_id' => $orderId]);
        $paymentStmt->execute([':order_id' => $orderId]);
        $order['id'] = $orderId;
        $order['total_amount'] = (float)$order['total_amount'];
        $order['items'] = $itemStmt->fetchAll();
        $order['payment'] = $paymentStmt->fetch() ?: null;
        $order['delivery'] = $order['recipient_name'] === null ? null : [
            'recipient_name' => $order['recipient_name'],
            'phone' => $order['phone'],
            'address_line' => $order['address_line'],
            'city' => $order['city'],
            'region' => $order['region'],
            'postal_code' => $order['postal_code'],
            'delivery_notes' => $order['delivery_notes'],
        ];
        unset(
            $order['recipient_name'],
            $order['phone'],
            $order['address_line'],
            $order['city'],
            $order['region'],
            $order['postal_code'],
            $order['delivery_notes']
        );
    }
    unset($order);

    echo json_encode(['success' => true, 'orders' => $orders, 'count' => count($orders)]);
} catch (Throwable $e) {
    error_log('ÆTHERYX admin order listing failed: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to retrieve orders.']);
}
