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

$equipmentId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$equipmentId || $equipmentId < 1) {
    respond(false, 'A valid equipment id is required.', ['code' => 'VALIDATION_ERROR'], 422);
}

try {

    $stmt = $pdo->prepare("
        SELECT
            e.id, e.category_id, e.access_role, e.name, e.description, e.brand,
            e.status, e.condition_status, e.quantity, e.available_quantity,
            e.image_url, e.created_at, e.updated_at,
            c.name AS category_name
        FROM equipment e
        LEFT JOIN categories c ON c.id = e.category_id
        WHERE e.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $equipmentId]);
    $equipment = $stmt->fetch();

    if (!$equipment) {
        respond(false, 'Equipment not found.', ['code' => 'NOT_FOUND'], 404);
    }

    /*
     * Open + recent maintenance requests for this equipment
     */
    $stmt = $pdo->prepare("
        SELECT
            mr.id, mr.requester_id, mr.assigned_to, mr.description,
            mr.reported_severity, mr.status, mr.resolution_notes,
            mr.created_at, mr.updated_at, mr.resolved_at,
            u.full_name AS requester_name, u.handle AS requester_handle
        FROM maintenance_requests mr
        LEFT JOIN users u ON u.id = mr.requester_id
        WHERE mr.equipment_id = :id
        ORDER BY mr.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([':id' => $equipmentId]);
    $maintenanceRequests = $stmt->fetchAll();

    $openCount = 0;
    foreach ($maintenanceRequests as $req) {
        if (in_array($req['status'], ['open', 'assigned', 'in_progress'], true)) {
            $openCount++;
        }
    }

    /*
     * Inventory history for this equipment (assignment history)
     */
    $stmt = $pdo->prepare("
        SELECT
            i.id, i.user_id, i.quantity, i.status, i.assigned_at, i.returned_at, i.notes,
            u.full_name AS user_full_name, u.handle AS user_handle
        FROM inventory i
        LEFT JOIN users u ON u.id = i.user_id
        WHERE i.equipment_id = :id
        ORDER BY i.assigned_at DESC
        LIMIT 20
    ");
    $stmt->execute([':id' => $equipmentId]);
    $inventoryHistory = $stmt->fetchAll();

    $damagedOrLostCount = 0;
    foreach ($inventoryHistory as $row) {
        if (in_array($row['status'], ['damaged', 'lost'], true)) {
            $damagedOrLostCount++;
        }
    }

    /*
     * Compatibility records for this equipment
     */
    $stmt = $pdo->prepare("
        SELECT c.resource_id, c.compatibility_type, c.notes, r.name AS resource_name
        FROM compatibility c
        LEFT JOIN resources r ON r.id = c.resource_id
        WHERE c.equipment_id = :id
    ");
    $stmt->execute([':id' => $equipmentId]);
    $compatibilityRows = $stmt->fetchAll();

    /*
     * Run the intelligence models
     */
    $health = sentinelAssetHealth($equipment, $openCount, $damagedOrLostCount);
    $lifecycle = sentinelLifecycleState($equipment, $health['score'], $openCount, $damagedOrLostCount);
    $pressure = sentinelInventoryPressure($equipment);
    $risk = sentinelCompatibilityRisk($compatibilityRows);
    $recommendation = sentinelRecommendation($health, $lifecycle, $pressure);

    respond(true, 'Asset details retrieved successfully.', [
        'asset' => [
            'id' => (int) $equipment['id'],
            'name' => $equipment['name'],
            'description' => $equipment['description'],
            'brand' => $equipment['brand'],
            'category' => $equipment['category_name'] ?? 'Uncategorized',
            'accessRole' => $equipment['access_role'],
            'status' => $equipment['status'],
            'conditionStatus' => $equipment['condition_status'],
            'quantity' => (int) $equipment['quantity'],
            'availableQuantity' => (int) $equipment['available_quantity'],
            'imageUrl' => $equipment['image_url'],
            'createdAt' => $equipment['created_at'],
            'updatedAt' => $equipment['updated_at']
        ],
        'sentinelAnalysis' => [
            'health' => $health,
            'lifecycle' => $lifecycle,
            'inventoryPressure' => $pressure,
            'compatibilityRisk' => $risk,
            'recommendation' => $recommendation
        ],
        'maintenanceRequests' => $maintenanceRequests,
        'inventoryHistory' => $inventoryHistory
    ]);

} catch (PDOException $e) {

    error_log('ÆTHERYX SENTINEL assets/details PDO error: ' . $e->getMessage());

    respond(false, 'Unable to retrieve asset details.', ['code' => 'DATABASE_ERROR'], 500);

} catch (Throwable $e) {

    error_log('ÆTHERYX SENTINEL assets/details error: ' . $e->getMessage());

    respond(false, 'An unexpected server error occurred.', ['code' => 'SERVER_ERROR'], 500);
}
