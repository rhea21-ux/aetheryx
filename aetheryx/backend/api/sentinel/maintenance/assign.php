<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/session.php';
startSecureSession();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/role-check.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/sentinel-intelligence.php';
require_once __DIR__ . '/../../../helpers/notify.php';

header('Content-Type: application/json; charset=utf-8');

requireSentinelAccess();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.', ['code' => 'METHOD_NOT_ALLOWED'], 405);
}

$actorId = getAuthenticatedUserId();

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    respond(false, 'Invalid JSON request.', ['code' => 'VALIDATION_ERROR'], 400);
}

$requestId = (int) ($data['request_id'] ?? 0);
$assigneeId = (int) ($data['assigned_to'] ?? 0);

if ($requestId <= 0 || $assigneeId <= 0) {
    respond(false, 'A valid request_id and assigned_to are required.', ['code' => 'VALIDATION_ERROR'], 422);
}

try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, requester_id, status FROM maintenance_requests WHERE id = :id FOR UPDATE
    ");
    $stmt->execute([':id' => $requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        $pdo->rollBack();
        respond(false, 'Maintenance request not found.', ['code' => 'NOT_FOUND'], 404);
    }

    if (!in_array($request['status'], ['open', 'assigned'], true)) {
        $pdo->rollBack();
        respond(
            false,
            sprintf('A request in "%s" status cannot be (re)assigned.', $request['status']),
            ['code' => 'INVALID_TRANSITION'],
            409
        );
    }

    /*
     * Admin-only for now (no 'staff' role exists yet — see requireSentinelAccess()),
     * so the assignee must themselves be an admin.
     */
    $stmt = $pdo->prepare("SELECT id, full_name, role FROM users WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $assigneeId]);
    $assignee = $stmt->fetch();

    if (!$assignee || $assignee['role'] !== 'admin') {
        $pdo->rollBack();
        respond(false, 'The assignee must be an existing administrator.', ['code' => 'VALIDATION_ERROR'], 422);
    }

    $oldStatus = $request['status'];

    $stmt = $pdo->prepare("
        UPDATE maintenance_requests SET assigned_to = :assigned_to, status = 'assigned' WHERE id = :id
    ");
    $stmt->execute([':assigned_to' => $assigneeId, ':id' => $requestId]);

    $stmt = $pdo->prepare("
        INSERT INTO maintenance_updates (request_id, actor_id, old_status, new_status, note)
        VALUES (:request_id, :actor_id, :old_status, 'assigned', :note)
    ");
    $stmt->execute([
        ':request_id' => $requestId,
        ':actor_id' => $actorId,
        ':old_status' => $oldStatus,
        ':note' => sprintf('Assigned to %s.', $assignee['full_name'])
    ]);

    sentinelLogActivity(
        $pdo,
        $actorId,
        'maintenance_request_assigned',
        sprintf('Maintenance request #%d assigned to %s.', $requestId, $assignee['full_name']),
        'maintenance_request',
        $requestId
    );

    sentinelNotify(
        $pdo,
        (int) $request['requester_id'],
        'Maintenance request assigned',
        sprintf('Your maintenance request #%d has been assigned to %s.', $requestId, $assignee['full_name']),
        'info'
    );

    sentinelNotify(
        $pdo,
        $assigneeId,
        'New maintenance assignment',
        sprintf('You have been assigned maintenance request #%d.', $requestId),
        'info'
    );

    $pdo->commit();
    $message = sprintf('Maintenance request #%d was assigned to %s.', $requestId, $assignee['full_name']);
    createNotification($pdo, (int) $actorId, 'Maintenance request assigned', $message, 'success');
    notifyAdminsOfActivity($pdo, (int) $actorId, 'Maintenance request assigned', $message, 'info');

    respond(true, 'Maintenance request assigned successfully.', [
        'requestId' => $requestId,
        'assignedTo' => $assigneeId
    ]);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('ÆTHERYX SENTINEL maintenance/assign PDO error: ' . $e->getMessage());

    respond(false, 'Unable to assign maintenance request.', ['code' => 'DATABASE_ERROR'], 500);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('ÆTHERYX SENTINEL maintenance/assign error: ' . $e->getMessage());

    respond(false, 'An unexpected server error occurred.', ['code' => 'SERVER_ERROR'], 500);
}
