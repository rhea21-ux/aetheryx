<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth-check.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$userId = getAuthenticatedUserId();
$data = json_decode((string) file_get_contents('php://input'), true);
$data = is_array($data) ? $data : [];

try {
    if (!empty($data['all_read'])) {
        $stmt = $pdo->prepare('DELETE FROM notifications WHERE user_id = :user_id AND is_read = 1');
        $stmt->execute([':user_id' => $userId]);
    } else {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            http_response_code(422);
            echo json_encode(['success' => false, 'message' => 'A valid notification ID is required.']);
            exit;
        }
        $stmt = $pdo->prepare('DELETE FROM notifications WHERE id = :id AND user_id = :user_id');
        $stmt->execute([':id' => $id, ':user_id' => $userId]);
    }

    echo json_encode(['success' => true, 'message' => 'Notifications deleted.']);
} catch (Throwable $e) {
    error_log('ÆTHERYX notifications/delete error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to delete notifications.']);
}
