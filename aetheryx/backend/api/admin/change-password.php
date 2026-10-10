<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/role-check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAdminRole();

$data = json_decode((string) file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request.']);
    exit;
}

$currentPassword = (string) ($data['current_password'] ?? '');
$newPassword = (string) ($data['new_password'] ?? '');
$confirmation = (string) ($data['new_password_confirmation'] ?? '');

if ($currentPassword === '' || $newPassword === '' || $confirmation === '') {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Complete all password fields.']);
    exit;
}

if (strlen($newPassword) < 12) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a password with at least 12 characters.']);
    exit;
}

if (strlen($newPassword) > 72) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a password no longer than 72 bytes.']);
    exit;
}

if (!hash_equals($newPassword, $confirmation)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'The new passwords do not match.']);
    exit;
}

if (hash_equals($currentPassword, $newPassword)) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Choose a password different from your current one.']);
    exit;
}

try {
    $userId = getAuthenticatedUserId();
    $statement = $pdo->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
    $statement->execute([':id' => $userId]);
    $user = $statement->fetch();

    if (!$user || !password_verify($currentPassword, (string) $user['password_hash'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
        exit;
    }

    $update = $pdo->prepare(
        'UPDATE users
         SET password_hash = :password_hash, updated_at = CURRENT_TIMESTAMP
         WHERE id = :id'
    );
    $update->execute([
        ':password_hash' => password_hash($newPassword, PASSWORD_DEFAULT),
        ':id' => $userId,
    ]);

    session_regenerate_id(true);

    echo json_encode([
        'success' => true,
        'message' => 'Administrator password updated successfully.',
    ]);
} catch (Throwable $e) {
    error_log('ÆTHERYX admin password update failed: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to update your password right now.']);
}
