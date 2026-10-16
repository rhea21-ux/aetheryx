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
$resolutionNotes = trim((string) ($data['resolution_notes'] ?? ''));

if ($requestId <= 0) {
    respond(false, 'A valid request_id is required.', ['code' => 'VALIDATION_ERROR'], 422);
}

if ($resolutionNotes === '' || mb_strlen($resolutionNotes) < 5) {
    respond(false, 'Resolution notes are required to close a request.', ['code' => 'VALIDATION_ERROR'], 422);
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

    if (!in_array($request['status'], ['assigned', 'in_progress'], true)) {
        $pdo->rollBack();
        respond(
            false,
            sprintf('A request in "%s" status cannot be resolved. Assign it first.', $request['status']),
            ['code' => 'INVALID_TRANSITION'],
            409
        );
    }

    $oldStatus = $request['status'];

    $stmt = $pdo->prepare("
        UPDATE maintenance_requests
        SET status = 'resolved', resolution_notes = :notes, resolved_at = CURRENT_TIMESTAMP
        WHERE id = :id
    ");
    $stmt->execute([':notes' => $resolutionNotes, ':id' => $requestId]);

    $stmt = $pdo->prepare("
        INSERT INTO maintenance_updates (request_id, actor_id, old_status, new_status, note)
        VALUES (:request_id, :actor_id, :old_status, 'resolved', :note)
    ");
    $stmt->execute([
        ':request_id' => $requestId,
        ':actor_id' => $actorId,
        ':old_status' => $oldStatus,
        ':note' => $resolutionNotes
    ]);

    sentinelLogActivity(
        $pdo,
        $actorId,
        'maintenance_request_resolved',
        sprintf('Maintenance request #%d resolved.', $requestId),
        'maintenance_request',
        $requestId
    );

    sentinelNotify(
        $pdo,
        (int) $request['requester_id'],
        'Maintenance request resolved',
        sprintf('Your maintenance request #%d has been resolved: %s', $requestId, $resolutionNotes),
        'success'
    );

    $pdo->commit();
    $message = sprintf('Maintenance request #%d was resolved.', $requestId);
    createNotification($pdo, (int) $actorId, 'Maintenance request resolved', $message, 'success');
    notifyAdminsOfActivity($pdo, (int) $actorId, 'Maintenance request resolved', $message, 'info');

    respond(true, 'Maintenance request resolved successfully.', [
        'requestId' => $requestId
    ]);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('ÆTHERYX SENTINEL maintenance/resolve PDO error: ' . $e->getMessage());

    respond(false, 'Unable to resolve maintenance request.', ['code' => 'DATABASE_ERROR'], 500);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('ÆTHERYX SENTINEL maintenance/resolve error: ' . $e->getMessage());

    respond(false, 'An unexpected server error occurred.', ['code' => 'SERVER_ERROR'], 500);
}
