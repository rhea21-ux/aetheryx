<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/role-check.php';
require_once __DIR__ . '/../../helpers/response.php';
require_once __DIR__ . '/../../helpers/sentinel-intelligence.php';

header('Content-Type: application/json; charset=utf-8');

requireSentinelAccess();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(false, 'Method not allowed.', ['code' => 'METHOD_NOT_ALLOWED'], 405);
}

try {

    /*
    |--------------------------------------------------------------------------
    | Base data
    |--------------------------------------------------------------------------
    */

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

    $compatByEquip = [];
    $stmt = $pdo->query("
        SELECT c.equipment_id, c.resource_id, c.compatibility_type, c.notes,
               r.name AS resource_name
        FROM compatibility c
        LEFT JOIN resources r ON r.id = c.resource_id
    ");
    foreach ($stmt->fetchAll() as $row) {
        $compatByEquip[(int) $row['equipment_id']][] = $row;
    }

    /*
    |--------------------------------------------------------------------------
    | Per-equipment intelligence pass
    |--------------------------------------------------------------------------
    */

    $healthScores = [];
    $lifecycleCounts = [
        'HEALTHY' => 0, 'MONITOR' => 0, 'ATTENTION' => 0,
        'CRITICAL' => 0, 'RETIREMENT_REVIEW' => 0
    ];
    $statusCounts = ['available' => 0, 'unavailable' => 0, 'maintenance' => 0];
    $compatibilityRiskCount = 0;
    $pressureAlertCount = 0;
    $recommendations = [];

    foreach ($equipment as $item) {

        $eqId = (int) $item['id'];
        $openCount = $openMaintByEquip[$eqId] ?? 0;
        $damagedCount = $damagedByEquip[$eqId] ?? 0;

        $health = sentinelAssetHealth($item, $openCount, $damagedCount);
        $lifecycle = sentinelLifecycleState($item, $health['score'], $openCount, $damagedCount);
        $pressure = sentinelInventoryPressure($item);
        $risk = sentinelCompatibilityRisk($compatByEquip[$eqId] ?? []);

        $healthScores[] = $health['score'];
        $lifecycleCounts[$lifecycle['state']]
            = ($lifecycleCounts[$lifecycle['state']] ?? 0) + 1;

        $itemStatus = (string) $item['status'];
        $statusCounts[$itemStatus] = ($statusCounts[$itemStatus] ?? 0) + 1;

        if (in_array($risk['riskLevel'], ['LOW', 'HIGH'], true)) {
            $compatibilityRiskCount++;
        }

        if (in_array($pressure['pressure'], ['HIGH', 'CRITICAL'], true)) {
            $pressureAlertCount++;
        }

        if (in_array($lifecycle['state'], ['ATTENTION', 'CRITICAL', 'RETIREMENT_REVIEW'], true)) {
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
    }

    usort(
        $recommendations,
        static fn(array $a, array $b): int => $a['healthScore'] <=> $b['healthScore']
    );
    $recommendations = array_slice($recommendations, 0, 5);

    $averageHealth = count($healthScores) > 0
        ? (int) round(array_sum($healthScores) / count($healthScores))
        : 0;

    /*
    |--------------------------------------------------------------------------
    | Service overview (maintenance_requests)
    |--------------------------------------------------------------------------
    */

    $requests = $pdo->query("
        SELECT id, equipment_id, status, reported_severity, created_at, resolved_at
        FROM maintenance_requests
    ")->fetchAll();

    $equipStatusById = [];
    foreach ($equipment as $item) {
        $equipStatusById[(int) $item['id']] = $item['status'];
    }

    $openRequests = 0;
    $pendingRequests = 0;
    $highPriorityCount = 0;
    $resolvedLast7Days = 0;
    $flaggedToday = 0;
    $today = (new DateTimeImmutable('today'))->format('Y-m-d');
    $sevenDaysAgo = (new DateTimeImmutable('-7 days'))->getTimestamp();

    foreach ($requests as $req) {

        $status = $req['status'];
        $eqId = (int) $req['equipment_id'];

        if (in_array($status, ['open', 'assigned', 'in_progress'], true)) {
            $openRequests++;

            $ageDays = (int) floor((time() - strtotime($req['created_at'])) / 86400);
            $otherOpen = max(0, ($openMaintByEquip[$eqId] ?? 1) - 1);

            $priorityResult = sentinelMaintenancePriority(
                $req['reported_severity'],
                $equipStatusById[$eqId] ?? 'available',
                $ageDays,
                $otherOpen
            );

            if (in_array($priorityResult['priority'], ['HIGH', 'CRITICAL'], true)) {
                $highPriorityCount++;
            }
        }

        if ($status === 'open') {
            $pendingRequests++;
        }

        if ($status === 'resolved' && $req['resolved_at'] !== null
            && strtotime($req['resolved_at']) >= $sevenDaysAgo
        ) {
            $resolvedLast7Days++;
        }

        if (substr((string) $req['created_at'], 0, 10) === $today) {
            $flaggedToday++;
        }
    }

    /*
    |--------------------------------------------------------------------------
    | Recent activity
    |--------------------------------------------------------------------------
    */

    $recentActivity = $pdo->query("
        SELECT id, user_id, action, description, entity_type, entity_id, created_at
        FROM activity_logs
        ORDER BY created_at DESC
        LIMIT 10
    ")->fetchAll();

    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    respond(true, 'Sentinel dashboard statistics retrieved successfully.', [
        'data' => [
            'assetOverview' => [
                'totalEquipment' => count($equipment),
                'healthy' => $lifecycleCounts['HEALTHY'],
                'monitor' => $lifecycleCounts['MONITOR'],
                'attention' => $lifecycleCounts['ATTENTION'],
                'critical' => $lifecycleCounts['CRITICAL'],
                'retirementReview' => $lifecycleCounts['RETIREMENT_REVIEW'],
                'underMaintenance' => $statusCounts['maintenance'] ?? 0,
                'unavailable' => $statusCounts['unavailable'] ?? 0
            ],
            'serviceOverview' => [
                'openRequests' => $openRequests,
                'pendingRequests' => $pendingRequests,
                'highPriorityRequests' => $highPriorityCount,
                'resolvedLast7Days' => $resolvedLast7Days
            ],
            'intelligence' => [
                'averageAssetHealth' => $averageHealth,
                'assetsFlaggedToday' => $flaggedToday,
                'compatibilityRiskCount' => $compatibilityRiskCount,
                'inventoryPressureAlerts' => $pressureAlertCount
            ],
            'recentActivity' => $recentActivity,
            'recommendations' => $recommendations
        ]
    ]);

} catch (PDOException $e) {

    error_log('ÆTHERYX SENTINEL dashboard-stats PDO error: ' . $e->getMessage());

    respond(false, 'Unable to load Sentinel dashboard statistics.', ['code' => 'DATABASE_ERROR'], 500);

} catch (Throwable $e) {

    error_log('ÆTHERYX SENTINEL dashboard-stats error: ' . $e->getMessage());

    respond(false, 'An unexpected server error occurred.', ['code' => 'SERVER_ERROR'], 500);
}
