<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/session.php';
startSecureSession();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/auth-check.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/sentinel-intelligence.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(false, 'Method not allowed.', ['code' => 'METHOD_NOT_ALLOWED'], 405);
}

$requestId = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$requestId || $requestId < 1) {
    respond(false, 'A valid request id is required.', ['code' => 'VALIDATION_ERROR'], 422);
}

$role = getAuthenticatedRole();
$userId = getAuthenticatedUserId();
$isAdmin = ($role === 'admin');

try {

    $stmt = $pdo->prepare("
        SELECT
            mr.id, mr.equipment_id, mr.requester_id, mr.assigned_to,
            mr.description, mr.reported_severity, mr.status,
            mr.resolution_notes, mr.created_at, mr.updated_at, mr.resolved_at,
            e.name AS equipment_name, e.status AS equipment_status, e.condition_status,
            req.full_name AS requester_name, req.handle AS requester_handle,
            asg.full_name AS assigned_name
        FROM maintenance_requests mr
        LEFT JOIN equipment e ON e.id = mr.equipment_id
        LEFT JOIN users req ON req.id = mr.requester_id
        LEFT JOIN users asg ON asg.id = mr.assigned_to
        WHERE mr.id = :id
        LIMIT 1
    ");
    $stmt->execute([':id' => $requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        respond(false, 'Maintenance request not found.', ['code' => 'NOT_FOUND'], 404);
    }

    if (!$isAdmin && (int) $request['requester_id'] !== $userId) {
        respond(false, 'You do not have access to this request.', ['code' => 'FORBIDDEN'], 403);
    }

    $stmt = $pdo->prepare("
        SELECT mu.id, mu.actor_id, mu.old_status, mu.new_status, mu.note, mu.created_at,
               u.full_name AS actor_name
        FROM maintenance_updates mu
        LEFT JOIN users u ON u.id = mu.actor_id
        WHERE mu.request_id = :id
        ORDER BY mu.created_at ASC
    ");
    $stmt->execute([':id' => $requestId]);
    $timeline = $stmt->fetchAll();

    $priority = null;

    if (in_array($request['status'], ['open', 'assigned', 'in_progress'], true)) {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM maintenance_requests
            WHERE equipment_id = :eq AND status IN ('open', 'assigned', 'in_progress') AND id != :id
        ");
        $stmt->execute([':eq' => $request['equipment_id'], ':id' => $requestId]);
        $otherOpen = (int) $stmt->fetchColumn();

        $ageDays = (int) floor((time() - strtotime($request['created_at'])) / 86400);

        $priority = sentinelMaintenancePriority(
            $request['reported_severity'],
            $request['equipment_status'] ?? 'available',
            $ageDays,
            $otherOpen
        );
    }

    respond(true, 'Maintenance request details retrieved successfully.', [
        'request' => [
            'id' => (int) $request['id'],
            'equipmentId' => (int) $request['equipment_id'],
            'equipmentName' => $request['equipment_name'],
            'equipmentStatus' => $request['equipment_status'],
            'equipmentCondition' => $request['condition_status'],
            'requesterId' => (int) $request['requester_id'],
            'requesterName' => $request['requester_name'],
            'assignedTo' => $request['assigned_to'] !== null ? (int) $request['assigned_to'] : null,
            'assignedName' => $request['assigned_name'],
            'description' => $request['description'],
            'reportedSeverity' => $request['reported_severity'],
            'status' => $request['status'],
            'resolutionNotes' => $request['resolution_notes'],
            'createdAt' => $request['created_at'],
            'updatedAt' => $request['updated_at'],
            'resolvedAt' => $request['resolved_at'],
            'priority' => $priority
        ],
        'timeline' => $timeline
    ]);

} catch (PDOException $e) {

    error_log('ÆTHERYX SENTINEL maintenance/details PDO error: ' . $e->getMessage());

    respond(false, 'Unable to retrieve request details.', ['code' => 'DATABASE_ERROR'], 500);

} catch (Throwable $e) {

    error_log('ÆTHERYX SENTINEL maintenance/details error: ' . $e->getMessage());

    respond(false, 'An unexpected server error occurred.', ['code' => 'SERVER_ERROR'], 500);
}
