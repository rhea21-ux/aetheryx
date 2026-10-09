<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/role-check.php';
require_once __DIR__ . '/../../helpers/response.php';

header('Content-Type: application/json; charset=utf-8');

requireSentinelAccess();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(false, 'Method not allowed.', ['code' => 'METHOD_NOT_ALLOWED'], 405);
}

try {

    $stmt = $pdo->query("
        SELECT
            al.id, al.user_id, al.action, al.description,
            al.entity_type, al.entity_id, al.ip_address, al.created_at,
            u.full_name, u.handle
        FROM activity_logs al
        LEFT JOIN users u ON u.id = al.user_id
        ORDER BY al.created_at DESC, al.id DESC
        LIMIT 200
    ");
    $logs = $stmt->fetchAll();

    respond(true, 'Activity logs retrieved successfully.', [
        'count' => count($logs),
        'logs' => $logs
    ]);

} catch (PDOException $e) {

    error_log('ÆTHERYX SENTINEL activity-logs PDO error: ' . $e->getMessage());

    respond(false, 'Unable to retrieve activity logs.', ['code' => 'DATABASE_ERROR'], 500);

} catch (Throwable $e) {

    error_log('ÆTHERYX SENTINEL activity-logs error: ' . $e->getMessage());

    respond(false, 'An unexpected server error occurred.', ['code' => 'SERVER_ERROR'], 500);
}
