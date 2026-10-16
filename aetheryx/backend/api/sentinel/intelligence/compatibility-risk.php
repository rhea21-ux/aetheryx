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

    $equipment = $pdo->query("SELECT id, name FROM equipment ORDER BY name ASC")->fetchAll();

    $compatByEquip = [];
    $stmt = $pdo->query("
        SELECT c.equipment_id, c.resource_id, c.compatibility_type, c.notes,
               r.name AS resource_name
        FROM equipment_resource_compatibility c
        LEFT JOIN resources r ON r.id = c.resource_id
    ");
    foreach ($stmt->fetchAll() as $row) {
        $compatByEquip[(int) $row['equipment_id']][] = $row;
    }

    $results = [];
    $summary = ['NONE' => 0, 'LOW' => 0, 'HIGH' => 0, 'UNKNOWN' => 0];

    foreach ($equipment as $item) {
        $eqId = (int) $item['id'];
        $risk = sentinelCompatibilityRisk($compatByEquip[$eqId] ?? []);

        $summary[$risk['riskLevel']] = ($summary[$risk['riskLevel']] ?? 0) + 1;

        $results[] = [
            'equipmentId' => $eqId,
            'equipmentName' => $item['name'],
            'riskLevel' => $risk['riskLevel'],
            'reason' => $risk['reason'],
            'recommendedAction' => $risk['recommendedAction'],
            'items' => $risk['items']
        ];
    }

    // Surface actual risks first, then unknowns, then clean records.
    $order = ['HIGH' => 0, 'LOW' => 1, 'UNKNOWN' => 2, 'NONE' => 3];
    usort($results, static fn($a, $b) => $order[$a['riskLevel']] <=> $order[$b['riskLevel']]);

    respond(true, 'Compatibility risk analysis complete.', [
        'summary' => $summary,
        'results' => $results
    ]);

} catch (PDOException $e) {

    error_log('ÆTHERYX SENTINEL intelligence/compatibility-risk PDO error: ' . $e->getMessage());

    respond(false, 'Unable to analyze compatibility risk.', ['code' => 'DATABASE_ERROR'], 500);

} catch (Throwable $e) {

    error_log('ÆTHERYX SENTINEL intelligence/compatibility-risk error: ' . $e->getMessage());

    respond(false, 'An unexpected server error occurred.', ['code' => 'SERVER_ERROR'], 500);
}
