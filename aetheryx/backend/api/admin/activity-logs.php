<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/role-check.php';

header('Content-Type: application/json; charset=utf-8');

requireAdminRole();

$limit = filter_var($_GET['limit'] ?? 20, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
$limit = $limit ?: 20;

try {
    $stmt = $pdo->prepare(
        'SELECT l.id, l.action, l.description, l.created_at, u.handle AS user_handle
         FROM activity_logs AS l
         LEFT JOIN users AS u ON u.id = l.user_id
         ORDER BY l.created_at DESC, l.id DESC
         LIMIT :limit'
    );
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->execute();

    echo json_encode(
        ['success' => true, 'data' => ['logs' => $stmt->fetchAll()]],
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
} catch (Throwable $e) {
    error_log('ÆTHERYX activity-logs error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to load activity logs.']);
}
