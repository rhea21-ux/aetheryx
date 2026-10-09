<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth-check.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
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

$equipmentId = filter_var(
    $data['equipment_id'] ?? null,
    FILTER_VALIDATE_INT
);

$quantity = filter_var(
    $data['quantity'] ?? 1,
    FILTER_VALIDATE_INT
);

if (!$equipmentId || $equipmentId < 1) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'A valid equipment ID is required.',
        'code' => 'INVALID_EQUIPMENT_ID'
    ]);

    exit;
}

if (!$quantity || $quantity < 1) {
    $quantity = 1;
}

try {

    $pdo->beginTransaction();

    /*
     * Lock the equipment row while checking stock.
     * This prevents two requests from incorrectly exceeding availability.
     */
    $equipmentStmt = $pdo->prepare("
        SELECT
            id,
            name,
            available_quantity,
            status
        FROM equipment
        WHERE id = :equipment_id
        FOR UPDATE
    ");

    $equipmentStmt->execute([
        ':equipment_id' => $equipmentId
    ]);

    $equipment = $equipmentStmt->fetch();

    if (!$equipment) {

        $pdo->rollBack();

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Equipment not found.',
            'code' => 'EQUIPMENT_NOT_FOUND'
        ]);

        exit;
    }

    if (strtolower((string) $equipment['status']) === 'maintenance') {

        $pdo->rollBack();

        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' => 'This equipment is currently unavailable.',
            'code' => 'EQUIPMENT_UNAVAILABLE'
        ]);

        exit;
    }

    $availableQuantity = (int) $equipment['available_quantity'];

    if ($availableQuantity < 1) {

        $pdo->rollBack();

        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' => 'This equipment is out of stock.',
            'code' => 'OUT_OF_STOCK'
        ]);

        exit;
    }

    /*
     * Check whether the user already has this item.
     */
    $existingStmt = $pdo->prepare("
        SELECT id, quantity
        FROM cart_items
        WHERE user_id = :user_id
          AND equipment_id = :equipment_id
        LIMIT 1
        FOR UPDATE
    ");

    $existingStmt->execute([
        ':user_id' => $userId,
        ':equipment_id' => $equipmentId
    ]);

    $existing = $existingStmt->fetch();

    if ($existing) {

        $newQuantity =
            (int) $existing['quantity'] + $quantity;

        if ($newQuantity > $availableQuantity) {

            $pdo->rollBack();

            http_response_code(409);

            echo json_encode([
                'success' => false,
                'message' =>
                    "Only {$availableQuantity} unit(s) are currently available.",
                'code' => 'INSUFFICIENT_STOCK'
            ]);

            exit;
        }

        $updateStmt = $pdo->prepare("
            UPDATE cart_items
            SET quantity = :quantity
            WHERE id = :id
              AND user_id = :user_id
        ");

        $updateStmt->execute([
            ':quantity' => $newQuantity,
            ':id' => (int) $existing['id'],
            ':user_id' => $userId
        ]);

        $finalQuantity = $newQuantity;

    } else {

        if ($quantity > $availableQuantity) {

            $pdo->rollBack();

            http_response_code(409);

            echo json_encode([
                'success' => false,
                'message' =>
                    "Only {$availableQuantity} unit(s) are currently available.",
                'code' => 'INSUFFICIENT_STOCK'
            ]);

            exit;
        }

        $insertStmt = $pdo->prepare("
            INSERT INTO cart_items
                (user_id, equipment_id, quantity)
            VALUES
                (:user_id, :equipment_id, :quantity)
        ");

        $insertStmt->execute([
            ':user_id' => $userId,
            ':equipment_id' => $equipmentId,
            ':quantity' => $quantity
        ]);

        $finalQuantity = $quantity;
    }

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Equipment added to cart.',
        'data' => [
            'equipment_id' => $equipmentId,
            'quantity' => $finalQuantity
        ]
    ]);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to add equipment to cart.',
        'code' => 'CART_ADD_FAILED'
    ]);
}