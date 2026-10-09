<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth-check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../helpers/notify.php';

header('Content-Type: application/json; charset=utf-8');

function respond(bool $success, string $message, int $status = 200, array $extra = []): never
{
    http_response_code($status);
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $extra),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.', 405, ['code' => 'METHOD_NOT_ALLOWED']);
}

$userId = getAuthenticatedUserId();
$data = json_decode((string) file_get_contents('php://input'), true);

$inventoryId = is_array($data)
    ? filter_var($data['inventory_id'] ?? null, FILTER_VALIDATE_INT)
    : false;

if (!$inventoryId || $inventoryId < 1) {
    respond(false, 'A valid inventory record is required.', 422, ['code' => 'INVALID_INVENTORY_ID']);
}

try {
    $pdo->beginTransaction();

    // Only the owner can return their own item.
    $itemStmt = $pdo->prepare(
        'SELECT id, equipment_id, quantity, status
         FROM inventory
         WHERE id = :id AND user_id = :user_id
         FOR UPDATE'
    );
    $itemStmt->execute([':id' => $inventoryId, ':user_id' => $userId]);
    $item = $itemStmt->fetch();

    if (!$item) {
        $pdo->rollBack();
        respond(false, 'Inventory record not found.', 404, ['code' => 'NOT_FOUND']);
    }

    if ($item['status'] !== 'assigned') {
        $pdo->rollBack();
        respond(false, 'Only assigned equipment can be returned.', 409, ['code' => 'NOT_RETURNABLE']);
    }

    $pdo->prepare(
        'UPDATE inventory
         SET status = "returned", returned_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    )->execute([':id' => $inventoryId]);

    // Put the units back into stock and reopen the item if it was sold out.
    $pdo->prepare(
        'UPDATE equipment
         SET available_quantity = available_quantity + :quantity,
             status = CASE WHEN status = "unavailable" THEN "available" ELSE status END
         WHERE id = :equipment_id'
    )->execute([
        ':quantity' => (int) $item['quantity'],
        ':equipment_id' => (int) $item['equipment_id']
    ]);

    createNotification($pdo, (int) $userId, 'Equipment returned', 'Your equipment was returned and is available to others again.', 'success');

    $pdo->commit();

    respond(true, 'Equipment returned successfully.');
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ÆTHERYX inventory/return error: ' . $e->getMessage());
    respond(false, 'Unable to return equipment.', 500, ['code' => 'RETURN_FAILED']);
}
