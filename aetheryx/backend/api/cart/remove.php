<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth-check.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
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

if (!$cartId || $cartId < 1) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'A valid cart ID is required.',
        'code' => 'INVALID_CART_ID'
    ]);

    exit;
}

try {

    $stmt = $pdo->prepare("
        DELETE FROM cart_items
        WHERE id = :cart_id
          AND user_id = :user_id
    ");

    $stmt->execute([
        ':cart_id' => $cartId,
        ':user_id' => $userId
    ]);

    if ($stmt->rowCount() === 0) {

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Cart item not found.',
            'code' => 'CART_ITEM_NOT_FOUND'
        ]);

        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Equipment removed from cart.',
        'data' => [
            'cart_id' => $cartId
        ]
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to remove cart item.',
        'code' => 'CART_REMOVE_FAILED'
    ]);
}