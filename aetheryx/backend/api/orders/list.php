<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
startSecureSession();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/role-check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireMarketplaceRole();
$userId = getAuthenticatedUserId();
$allowedStatuses = [
    'pending',
    'confirmed',
    'processing',
    'ready_for_delivery',
    'shipped',
    'out_for_delivery',
    'delivered',
    'completed',
    'cancelled',
];
$status = strtolower(trim((string)($_GET['status'] ?? '')));
$search = trim((string)($_GET['search'] ?? ''));
$page = filter_var($_GET['page'] ?? 1, FILTER_VALIDATE_INT);
$perPage = filter_var($_GET['per_page'] ?? 10, FILTER_VALIDATE_INT);
$sort = strtolower(trim((string)($_GET['sort'] ?? 'newest')));

if ($status !== '' && !in_array($status, $allowedStatuses, true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order status filter.']);
    exit;
}
if ($page === false || $page < 1 || $perPage === false || !in_array($perPage, [10, 20, 50], true)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order page.']);
    exit;
}
if (strlen($search) > 120) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Order search must be 120 characters or fewer.']);
    exit;
}

$sortSql = [
    'newest' => 'o.created_at DESC, o.id DESC',
    'oldest' => 'o.created_at ASC, o.id ASC',
    'total_high' => 'o.total_amount DESC, o.created_at DESC, o.id DESC',
    'total_low' => 'o.total_amount ASC, o.created_at DESC, o.id DESC',
];
if (!array_key_exists($sort, $sortSql)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid order sort option.']);
    exit;
}

try {
    $conditions = ['o.user_id = :user_id'];
    $parameters = [':user_id' => $userId];
    if ($status !== '') {
        $conditions[] = 'o.order_status = :status';
        $parameters[':status'] = $status;
    }
    if ($search !== '') {
        $conditions[] = '(o.order_number LIKE :order_search
            OR EXISTS (
                SELECT 1 FROM order_items search_item
                WHERE search_item.order_id = o.id
                  AND search_item.equipment_name LIKE :item_search
            ))';
        $searchTerm = '%' . $search . '%';
        $parameters[':order_search'] = $searchTerm;
        $parameters[':item_search'] = $searchTerm;
    }
    $whereSql = implode(' AND ', $conditions);

    $countStmt = $pdo->prepare("SELECT COUNT(*) FROM orders o WHERE {$whereSql}");
    $countStmt->execute($parameters);
    $total = (int)$countStmt->fetchColumn();
    $pages = max(1, (int)ceil($total / $perPage));
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $perPage;

    $stmt = $pdo->prepare(
        'SELECT o.id, o.order_number, o.order_status, o.payment_method, o.payment_status,
                o.cancellation_reason, o.cancelled_at, o.total_amount, o.created_at,
                d.recipient_name, d.phone, d.address_line, d.city, d.region, d.postal_code, d.delivery_notes
         FROM orders o
         LEFT JOIN order_delivery_details d ON d.order_id = o.id
         WHERE ' . $whereSql . '
         ORDER BY ' . $sortSql[$sort] . '
         LIMIT :limit OFFSET :offset'
    );
    foreach ($parameters as $name => $value) {
        $stmt->bindValue($name, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
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

    echo json_encode([
        'success' => true,
        'orders' => $orders,
        'count' => count($orders),
        'total' => $total,
        'page' => $page,
        'per_page' => $perPage,
        'pages' => $pages,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $e) {
    error_log('ÆTHERYX order history failed: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to retrieve order history.']);
}
