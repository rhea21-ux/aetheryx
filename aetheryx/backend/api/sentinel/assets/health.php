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

$equipmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$equipmentId || $equipmentId < 1) {
    respond(false, 'A valid equipment id is required.', ['code' => 'VALIDATION_ERROR'], 422);
}

try {

    $stmt = $pdo->prepare("
        SELECT id, status, condition_status, quantity, available_quantity
        FROM equipment
        WHERE id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $equipmentId]);
    $equipment = $stmt->fetch();

    if (!$equipment) {
        respond(false, 'Equipment not found.', ['code' => 'NOT_FOUND'], 404);
    }

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM maintenance_requests
        WHERE equipment_id = :id AND status IN ('open', 'assigned', 'in_progress')
    ");
    $stmt->execute([':id' => $equipmentId]);
    $openCount = (int) $stmt->fetchColumn();

    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM inventory
        WHERE equipment_id = :id AND status IN ('damaged', 'lost')
    ");
    $stmt->execute([':id' => $equipmentId]);
    $damagedCount = (int) $stmt->fetchColumn();

    $health = sentinelAssetHealth($equipment, $openCount, $damagedCount);
    $lifecycle = sentinelLifecycleState($equipment, $health['score'], $openCount, $damagedCount);

    respond(true, 'Asset health recalculated.', [
        'equipmentId' => (int) $equipment['id'],
        'health' => $health,
        'lifecycle' => $lifecycle
    ]);

} catch (PDOException $e) {

    error_log('ÆTHERYX SENTINEL assets/health PDO error: ' . $e->getMessage());

    respond(false, 'Unable to calculate asset health.', ['code' => 'DATABASE_ERROR'], 500);

} catch (Throwable $e) {

    error_log('ÆTHERYX SENTINEL assets/health error: ' . $e->getMessage());

    respond(false, 'An unexpected server error occurred.', ['code' => 'SERVER_ERROR'], 500);
}
