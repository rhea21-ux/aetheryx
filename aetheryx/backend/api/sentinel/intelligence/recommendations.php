<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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
        SELECT id, category_id, access_role, name, status, condition_status,
               quantity, available_quantity
        FROM equipment
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

    $recommendations = [];

    foreach ($equipment as $item) {

        $eqId = (int) $item['id'];
        $openCount = $openMaintByEquip[$eqId] ?? 0;
        $damagedCount = $damagedByEquip[$eqId] ?? 0;

        $health = sentinelAssetHealth($item, $openCount, $damagedCount);
        $lifecycle = sentinelLifecycleState($item, $health['score'], $openCount, $damagedCount);

        if (!in_array($lifecycle['state'], ['ATTENTION', 'CRITICAL', 'RETIREMENT_REVIEW'], true)) {
            continue;
        }

        $pressure = sentinelInventoryPressure($item);
        $rec = sentinelRecommendation($health, $lifecycle, $pressure);

        $recommendations[] = [
            'equipmentId' => $eqId,
            'equipmentName' => $item['name'],
            'lifecycleState' => $lifecycle['state'],
            'healthScore' => $health['score'],
            'action' => $rec['action'],
            'why' => $rec['why']
        ];
    }

    usort(
        $recommendations,
        static fn(array $a, array $b): int => $a['healthScore'] <=> $b['healthScore']
    );

    respond(true, 'Sentinel recommendations retrieved successfully.', [
        'count' => count($recommendations),
        'recommendations' => $recommendations
    ]);

} catch (PDOException $e) {

    error_log('ÆTHERYX SENTINEL intelligence/recommendations PDO error: ' . $e->getMessage());

    respond(false, 'Unable to generate recommendations.', ['code' => 'DATABASE_ERROR'], 500);

} catch (Throwable $e) {

    error_log('ÆTHERYX SENTINEL intelligence/recommendations error: ' . $e->getMessage());

    respond(false, 'An unexpected server error occurred.', ['code' => 'SERVER_ERROR'], 500);
}
