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
        SELECT e.id, e.name, e.category_id, e.quantity, e.available_quantity,
               c.name AS category_name
        FROM equipment e
        LEFT JOIN categories c ON c.id = e.category_id
        ORDER BY e.name ASC
    ")->fetchAll();

    $perItem = array_map(static function (array $item): array {
        $pressure = sentinelInventoryPressure($item);

        return [
            'equipmentId' => (int) $item['id'],
            'equipmentName' => $item['name'],
            'category' => $item['category_name'] ?? 'Uncategorized',
            'quantity' => (int) $item['quantity'],
            'availableQuantity' => (int) $item['available_quantity'],
            'pressure' => $pressure['pressure'],
            'availabilityPercent' => $pressure['availabilityPercent'],
            'reason' => $pressure['reason']
        ];
    }, $equipment);

    // Worst pressure first.
    $order = ['CRITICAL' => 0, 'HIGH' => 1, 'MEDIUM' => 2, 'LOW' => 3, 'UNKNOWN' => 4];
    usort($perItem, static fn($a, $b) => $order[$a['pressure']] <=> $order[$b['pressure']]);

    $categoryRollup = sentinelInventoryPressureByCategory($equipment);
    $categoryNameById = [];
    foreach ($equipment as $item) {
        $categoryNameById[$item['category_id'] ?? 'uncategorized'] = $item['category_name'] ?? 'Uncategorized';
    }

    $categoryResults = [];
    foreach ($categoryRollup as $categoryId => $result) {
        $categoryResults[] = [
            'category' => $categoryNameById[$categoryId] ?? 'Uncategorized',
            'pressure' => $result['pressure'],
            'availabilityPercent' => $result['availabilityPercent'],
            'reason' => $result['reason']
        ];
    }
    usort($categoryResults, static fn($a, $b) => $order[$a['pressure']] <=> $order[$b['pressure']]);

    respond(true, 'Inventory pressure analysis complete.', [
        'byEquipment' => $perItem,
        'byCategory' => $categoryResults
    ]);

} catch (PDOException $e) {

    error_log('ÆTHERYX SENTINEL intelligence/inventory-pressure PDO error: ' . $e->getMessage());

    respond(false, 'Unable to analyze inventory pressure.', ['code' => 'DATABASE_ERROR'], 500);

} catch (Throwable $e) {

    error_log('ÆTHERYX SENTINEL intelligence/inventory-pressure error: ' . $e->getMessage());

    respond(false, 'An unexpected server error occurred.', ['code' => 'SERVER_ERROR'], 500);
}
