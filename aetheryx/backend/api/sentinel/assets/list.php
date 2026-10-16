<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/session.php';
startSecureSession();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/role-check.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/sentinel-intelligence.php';

header('Content-Type: application/json; charset=utf-8');

requireSentinelAccess();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(false, 'Method not allowed.', ['code' => 'METHOD_NOT_ALLOWED'], 405);
}

try {

    $equipment = $pdo->query("
        SELECT
            e.id, e.category_id, e.access_role, e.name, e.brand,
            e.status, e.condition_status, e.quantity, e.available_quantity,
            e.image_url, e.updated_at,
            c.name AS category_name
        FROM equipment e
        LEFT JOIN categories c ON c.id = e.category_id
        ORDER BY e.id ASC
    ")->fetchAll();

    $openMaintByEquip = [];
    $stmt = $pdo->query("
        SELECT equipment_id, COUNT(*) AS cnt
        FROM maintenance_requests
        WHERE status IN ('open', 'assigned', 'in_progress')
        GROUP BY equipment_id
    ");
    foreach ($stmt->fetchAll() as $row) {
        $openMaintByEquip[(int) $row['equipment_id']] = (int) $row['cnt'];
    }

    $damagedByEquip = [];
    $stmt = $pdo->query("
        SELECT equipment_id, COUNT(*) AS cnt
        FROM inventory
        WHERE status IN ('damaged', 'lost')
        GROUP BY equipment_id
    ");
    foreach ($stmt->fetchAll() as $row) {
        $damagedByEquip[(int) $row['equipment_id']] = (int) $row['cnt'];
    }

    $items = array_map(static function (array $item) use ($openMaintByEquip, $damagedByEquip): array {

        $eqId = (int) $item['id'];
        $openCount = $openMaintByEquip[$eqId] ?? 0;
        $damagedCount = $damagedByEquip[$eqId] ?? 0;

        $health = sentinelAssetHealth($item, $openCount, $damagedCount);
        $lifecycle = sentinelLifecycleState($item, $health['score'], $openCount, $damagedCount);
        $pressure = sentinelInventoryPressure($item);

        return [
            'id' => $eqId,
            'name' => $item['name'],
            'brand' => $item['brand'],
            'category' => $item['category_name'] ?? 'Uncategorized',
            'accessRole' => $item['access_role'],
            'status' => $item['status'],
            'conditionStatus' => $item['condition_status'],
            'quantity' => (int) $item['quantity'],
            'availableQuantity' => (int) $item['available_quantity'],
            'imageUrl' => $item['image_url'],
            'updatedAt' => $item['updated_at'],
            'healthScore' => $health['score'],
            'healthBand' => $health['band'],
            'lifecycleState' => $lifecycle['state'],
            'inventoryPressure' => $pressure['pressure'],
            'openMaintenanceCount' => $openCount
        ];
    }, $equipment);

    respond(true, 'Sentinel asset list retrieved successfully.', [
        'count' => count($items),
        'assets' => $items
    ]);

} catch (PDOException $e) {

    error_log('ÆTHERYX SENTINEL assets/list PDO error: ' . $e->getMessage());

    respond(false, 'Unable to retrieve assets.', ['code' => 'DATABASE_ERROR'], 500);

} catch (Throwable $e) {

    error_log('ÆTHERYX SENTINEL assets/list error: ' . $e->getMessage());

    respond(false, 'An unexpected server error occurred.', ['code' => 'SERVER_ERROR'], 500);
}
