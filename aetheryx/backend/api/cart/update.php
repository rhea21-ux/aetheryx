<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth-check.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
        'code' => 'METHOD_NOT_ALLOWED'
    ]);

    exit;
}

$userId = getAuthenticatedUserId();

$data = json_decode(
    file_get_contents('php://input'),
    true
);

if (!is_array($data)) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON request.',
        'code' => 'INVALID_JSON'
    ]);

    exit;
}

$cartId = filter_var(
    $data['cart_id'] ?? null,
    FILTER_VALIDATE_INT
);

$quantity = filter_var(
    $data['quantity'] ?? null,
    FILTER_VALIDATE_INT
);

if (!$cartId || $cartId < 1 || !$quantity || $quantity < 1) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Valid cart ID and quantity are required.',
        'code' => 'INVALID_INPUT'
    ]);

    exit;
}

try {

    $stmt = $pdo->prepare("
        SELECT
            ci.id,
            ci.equipment_id,
            e.available_quantity,
            e.status
        FROM cart_items ci

        INNER JOIN equipment e
            ON e.id = ci.equipment_id

        WHERE ci.id = :cart_id
          AND ci.user_id = :user_id

        LIMIT 1
    ");

    $stmt->execute([
        ':cart_id' => $cartId,
        ':user_id' => $userId
    ]);

    $item = $stmt->fetch();

    if (!$item) {

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Cart item not found.',
            'code' => 'CART_ITEM_NOT_FOUND'
        ]);

        exit;
    }

    if (strtolower((string) $item['status']) === 'maintenance') {

        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' => 'This equipment is currently unavailable.',
            'code' => 'EQUIPMENT_UNAVAILABLE'
        ]);

        exit;
    }

    $availableQuantity = (int) $item['available_quantity'];

    if ($quantity > $availableQuantity) {

        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' =>
                "Only {$availableQuantity} unit(s) are currently available.",
            'code' => 'INSUFFICIENT_STOCK'
        ]);

        exit;
    }

    $update = $pdo->prepare("
        UPDATE cart_items
        SET quantity = :quantity
        WHERE id = :cart_id
          AND user_id = :user_id
    ");

    $update->execute([
        ':quantity' => $quantity,
        ':cart_id' => $cartId,
        ':user_id' => $userId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Cart quantity updated.',
        'data' => [
            'cart_id' => $cartId,
            'quantity' => $quantity
        ]
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to update cart.',
        'code' => 'CART_UPDATE_FAILED'
    ]);
}