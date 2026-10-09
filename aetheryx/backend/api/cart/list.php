<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth-check.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
        'code' => 'METHOD_NOT_ALLOWED'
    ]);

    exit;
}

$userId = getAuthenticatedUserId();

try {

    $stmt = $pdo->prepare("
        SELECT
            ci.id,
            ci.equipment_id,
            ci.quantity,
            ci.created_at,
            ci.updated_at,

            e.name,
            e.description,
            e.brand,
            e.quantity AS total_quantity,
            e.available_quantity,
            e.status,
            e.image_url,

            c.name AS category_name

        FROM cart_items ci

        INNER JOIN equipment e
            ON e.id = ci.equipment_id

        LEFT JOIN categories c
            ON c.id = e.category_id

        WHERE ci.user_id = :user_id

        ORDER BY ci.created_at DESC
    ");

    $stmt->execute([
        ':user_id' => $userId
    ]);

    $items = $stmt->fetchAll();

    $totalItems = 0;

    foreach ($items as &$item) {

        $item['id'] = (int) $item['id'];
        $item['equipment_id'] = (int) $item['equipment_id'];
        $item['quantity'] = (int) $item['quantity'];
        $item['total_quantity'] = (int) $item['total_quantity'];
        $item['available_quantity'] = (int) $item['available_quantity'];

        $totalItems += $item['quantity'];
    }

    unset($item);

    echo json_encode([
        'success' => true,
        'message' => 'Cart retrieved successfully.',
        'data' => [
            'items' => $items,
            'total_items' => $totalItems,
            'item_count' => count($items)
        ]
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to retrieve cart.',
        'code' => 'CART_FETCH_FAILED'
    ]);
}