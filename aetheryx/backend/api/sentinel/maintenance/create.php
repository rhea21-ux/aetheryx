<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/auth-check.php';
require_once __DIR__ . '/../../../helpers/response.php';

header('Content-Type: application/json; charset=utf-8');

// Any authenticated user may submit a maintenance request — not admin-only.
requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    respond(false, 'Method not allowed.', ['code' => 'METHOD_NOT_ALLOWED'], 405);
}

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$equipmentId = filter_var($input['equipment_id'] ?? null, FILTER_VALIDATE_INT);
$description = trim((string) ($input['description'] ?? ''));
$severity = (string) ($input['reported_severity'] ?? 'normal');

$allowedSeverities = ['low', 'normal', 'high', 'critical'];

if (!$equipmentId || $equipmentId < 1) {
    respond(false, 'A valid equipment_id is required.', ['code' => 'VALIDATION_ERROR'], 422);
}

if ($description === '' || mb_strlen($description) > 2000) {
    respond(false, 'A description is required (max 2000 characters).', ['code' => 'VALIDATION_ERROR'], 422);
}

if (!in_array($severity, $allowedSeverities, true)) {
    respond(false, 'Invalid reported_severity.', ['code' => 'VALIDATION_ERROR'], 422);
}

try {

    $stmt = $pdo->prepare("SELECT id, name FROM equipment WHERE id = :id LIMIT 1");
    $stmt->execute([':id' => $equipmentId]);
    $equipment = $stmt->fetch();

    if (!$equipment) {
        respond(false, 'Equipment not found.', ['code' => 'NOT_FOUND'], 404);
    }

    $requesterId = getAuthenticatedUserId();

    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        INSERT INTO maintenance_requests
            (equipment_id, requester_id, description, reported_severity, status)
        VALUES
            (:equipment_id, :requester_id, :description, :severity, 'open')
    ");
    $stmt->execute([
        ':equipment_id' => $equipmentId,
        ':requester_id' => $requesterId,
        ':description' => $description,
        ':severity' => $severity
    ]);

    $requestId = (int) $pdo->lastInsertId();

    $stmt = $pdo->prepare("
        INSERT INTO maintenance_updates (request_id, actor_id, old_status, new_status, note)
        VALUES (:request_id, :actor_id, NULL, 'open', 'Request submitted')
    ");
    $stmt->execute([':request_id' => $requestId, ':actor_id' => $requesterId]);

    $stmt = $pdo->prepare("
        INSERT INTO activity_logs (user_id, action, description, entity_type, entity_id)
        VALUES (:user_id, 'maintenance_request_created', :description, 'maintenance_request', :entity_id)
    ");
    $stmt->execute([
        ':user_id' => $requesterId,
        ':description' => sprintf('Maintenance request submitted for "%s"', $equipment['name']),
        ':entity_id' => $requestId
    ]);

    // Notify admins
    $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll();
    $notifyStmt = $pdo->prepare("
        INSERT INTO notifications (user_id, title, message, type)
        VALUES (:user_id, :title, :message, 'info')
    ");
    foreach ($admins as $admin) {
        $notifyStmt->execute([
            ':user_id' => $admin['id'],
            ':title' => 'New maintenance request',
            ':message' => sprintf('A new maintenance request was submitted for "%s".', $equipment['name'])
        ]);
    }

    $pdo->commit();

    respond(true, 'Maintenance request submitted successfully.', [
        'requestId' => $requestId
    ], 201);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('ÆTHERYX SENTINEL maintenance/create PDO error: ' . $e->getMessage());

    respond(false, 'Unable to submit maintenance request.', ['code' => 'DATABASE_ERROR'], 500);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('ÆTHERYX SENTINEL maintenance/create error: ' . $e->getMessage());

    respond(false, 'An unexpected server error occurred.', ['code' => 'SERVER_ERROR'], 500);
}
