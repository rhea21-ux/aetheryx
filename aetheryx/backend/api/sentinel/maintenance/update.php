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

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.', ['code' => 'METHOD_NOT_ALLOWED'], 405);
}

$actorId = getAuthenticatedUserId();

$data = json_decode(file_get_contents('php://input'), true);

if (!is_array($data)) {
    respond(false, 'Invalid JSON request.', ['code' => 'VALIDATION_ERROR'], 400);
}

$requestId = (int) ($data['request_id'] ?? 0);
$newStatus = (string) ($data['status'] ?? '');
$note = trim((string) ($data['note'] ?? ''));

if ($requestId <= 0) {
    respond(false, 'A valid request_id is required.', ['code' => 'VALIDATION_ERROR'], 422);
}

/*
 * Resolution goes through resolve.php (requires resolution_notes).
 * Assignment goes through assign.php (requires assigned_to).
 * This endpoint only ever handles these three transitions.
 */
$allowedTargets = ['in_progress', 'open', 'cancelled'];

if (!in_array($newStatus, $allowedTargets, true)) {
    respond(
        false,
        'This endpoint only accepts status: in_progress, open, or cancelled. '
            . 'Use resolve.php to resolve a request or assign.php to assign one.',
        ['code' => 'VALIDATION_ERROR'],
        422
    );
}

$allowedTransitions = [
    'open' => ['in_progress', 'cancelled'],
    'assigned' => ['in_progress', 'cancelled', 'open'],
    'in_progress' => ['open', 'cancelled'],
];

try {

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, requester_id, equipment_id, status
        FROM maintenance_requests
        WHERE id = :id
        FOR UPDATE
    ");
    $stmt->execute([':id' => $requestId]);
    $request = $stmt->fetch();

    if (!$request) {
        $pdo->rollBack();
        respond(false, 'Maintenance request not found.', ['code' => 'NOT_FOUND'], 404);
    }

    $currentStatus = $request['status'];

    if (!isset($allowedTransitions[$currentStatus])
        || !in_array($newStatus, $allowedTransitions[$currentStatus], true)
    ) {
        $pdo->rollBack();
        respond(
            false,
            sprintf('Cannot move a request from "%s" to "%s".', $currentStatus, $newStatus),
            ['code' => 'INVALID_TRANSITION'],
            409
        );
    }

    $stmt = $pdo->prepare("
        UPDATE maintenance_requests SET status = :status WHERE id = :id
    ");
    $stmt->execute([':status' => $newStatus, ':id' => $requestId]);

    $stmt = $pdo->prepare("
        INSERT INTO maintenance_updates (request_id, actor_id, old_status, new_status, note)
        VALUES (:request_id, :actor_id, :old_status, :new_status, :note)
    ");
    $stmt->execute([
        ':request_id' => $requestId,
        ':actor_id' => $actorId,
        ':old_status' => $currentStatus,
        ':new_status' => $newStatus,
        ':note' => $note !== '' ? $note : null
    ]);

    sentinelLogActivity(
        $pdo,
        $actorId,
        'maintenance_request_updated',
        sprintf('Maintenance request #%d moved from "%s" to "%s".', $requestId, $currentStatus, $newStatus),
        'maintenance_request',
        $requestId
    );

    sentinelNotify(
        $pdo,
        (int) $request['requester_id'],
        'Maintenance request updated',
        sprintf('Your maintenance request #%d is now "%s".', $requestId, $newStatus),
        'info'
    );

    $pdo->commit();

    respond(true, 'Maintenance request updated successfully.', [
        'requestId' => $requestId,
        'status' => $newStatus
    ]);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('ÆTHERYX SENTINEL maintenance/update PDO error: ' . $e->getMessage());

    respond(false, 'Unable to update maintenance request.', ['code' => 'DATABASE_ERROR'], 500);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('ÆTHERYX SENTINEL maintenance/update error: ' . $e->getMessage());

    respond(false, 'An unexpected server error occurred.', ['code' => 'SERVER_ERROR'], 500);
}
