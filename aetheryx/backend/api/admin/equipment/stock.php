<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/session.php';
startSecureSession();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/role-check.php';
require_once __DIR__ . '/../../../helpers/notify.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

function equipmentStockRespond(bool $success, string $message, array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

requireAdminRole();
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

try {
    if ($method === 'GET') {
        $items = $pdo->query(
            'SELECT id, name, access_role, quantity, available_quantity, status, is_black_market
             FROM equipment
             ORDER BY name, id'
        )->fetchAll(PDO::FETCH_ASSOC);
        equipmentStockRespond(true, 'Equipment stock loaded.', [
            'equipment' => array_map(
                static fn(array $item): array => [
                    'id' => (int) $item['id'],
                    'name' => (string) $item['name'],
                    'access_role' => (string) $item['access_role'],
                    'quantity' => (int) $item['quantity'],
                    'available_quantity' => (int) $item['available_quantity'],
                    'status' => (string) $item['status'],
                    'is_black_market' => (bool) $item['is_black_market'],
                ],
                $items
            ),
        ]);
    }

    if (!in_array($method, ['POST', 'PATCH'], true)) {
        equipmentStockRespond(false, 'Method not allowed.', [], 405);
    }

    $data = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($data)) {
        equipmentStockRespond(false, 'Invalid JSON request.', [], 400);
    }

    $equipmentId = filter_var($data['equipment_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$equipmentId || $equipmentId < 1) {
        equipmentStockRespond(false, 'A valid equipment ID is required.', [], 422);
    }

    if ($method === 'PATCH') {
        $blackMarketValue = $data['is_black_market'] ?? null;
        if (!in_array($blackMarketValue, [0, 1, false, true], true)) {
            equipmentStockRespond(false, 'Choose whether this equipment is listed in the Black Market.', [], 422);
        }
        $statement = $pdo->prepare(
            'UPDATE equipment SET is_black_market = :is_black_market WHERE id = :equipment_id'
        );
        $statement->execute([
            ':is_black_market' => (int) $blackMarketValue,
            ':equipment_id' => $equipmentId,
        ]);
        if ($statement->rowCount() === 0) {
            $exists = $pdo->prepare('SELECT 1 FROM equipment WHERE id = :equipment_id');
            $exists->execute([':equipment_id' => $equipmentId]);
            if (!$exists->fetchColumn()) {
                equipmentStockRespond(false, 'Equipment not found.', [], 404);
            }
        }
        $adminId = (int) getAuthenticatedUserId();
        $message = sprintf('%s the Black Market listing for equipment #%d.', $blackMarketValue ? 'Enabled' : 'Disabled', $equipmentId);
        createNotification($pdo, $adminId, 'Black Market setting updated', $message, 'success');
        notifyAdminsOfActivity($pdo, $adminId, 'Black Market setting updated', $message, 'info');
        equipmentStockRespond(true, 'Black Market listing updated.', [
            'equipment_id' => (int) $equipmentId,
            'is_black_market' => (bool) $blackMarketValue,
        ]);
    }

    $restockQuantity = filter_var($data['quantity'] ?? null, FILTER_VALIDATE_INT);
    if (!$restockQuantity || $restockQuantity < 1 || $restockQuantity > 2147483647) {
        equipmentStockRespond(false, 'Restock quantity must be a whole number from 1 to 2,147,483,647.', [], 422);
    }

    $pdo->beginTransaction();
    $lock = $pdo->prepare(
        'SELECT name, quantity, available_quantity
         FROM equipment
         WHERE id = :equipment_id
         FOR UPDATE'
    );
    $lock->execute([':equipment_id' => $equipmentId]);
    $item = $lock->fetch(PDO::FETCH_ASSOC);
    if (!$item) {
        $pdo->rollBack();
        equipmentStockRespond(false, 'Equipment not found.', [], 404);
    }

    $maximum = 2147483647;
    if ((int) $item['quantity'] > $maximum - $restockQuantity
        || (int) $item['available_quantity'] > $maximum - $restockQuantity) {
        $pdo->rollBack();
        equipmentStockRespond(false, 'Restocking would exceed the supported stock limit.', [], 422);
    }

    $statusUpdate = $pdo->prepare(
        'UPDATE equipment
         SET quantity = quantity + :restock_quantity,
             available_quantity = available_quantity + :available_restock_quantity,
             status = CASE WHEN status = \'unavailable\' THEN \'available\' ELSE status END,
             is_black_market = 1
         WHERE id = :equipment_id'
    );
    $statusUpdate->execute([
        ':restock_quantity' => $restockQuantity,
        ':available_restock_quantity' => $restockQuantity,
        ':equipment_id' => $equipmentId,
    ]);
    $pdo->commit();
    $adminId = (int) getAuthenticatedUserId();
    $message = sprintf('Restocked %s with %d unit(s).', $item['name'], $restockQuantity);
    createNotification($pdo, $adminId, 'Equipment restocked', $message, 'success');
    notifyAdminsOfActivity($pdo, $adminId, 'Equipment restocked', $message, 'info');

    equipmentStockRespond(true, 'Equipment restocked.', [
        'equipment_id' => (int) $equipmentId,
        'quantity_added' => $restockQuantity,
        'quantity' => (int) $item['quantity'] + $restockQuantity,
        'available_quantity' => (int) $item['available_quantity'] + $restockQuantity,
        'is_black_market' => true,
    ]);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ÆTHERYX equipment stock management failed: ' . get_class($e) . ': ' . $e->getMessage());
    equipmentStockRespond(false, 'Unable to update equipment stock.', ['code' => 'STOCK_UPDATE_FAILED'], 500);
}
