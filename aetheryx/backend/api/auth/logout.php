<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
startSecureSession();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth-check.php';
require_once __DIR__ . '/../../helpers/notify.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAuth();
$userId = getAuthenticatedUserId();
revokeTabAuthSession($pdo, (string) ($_SERVER['HTTP_X_AETHERYX_TAB_SESSION'] ?? ''));
if ($userId !== null) {
    createNotification($pdo, $userId, 'Signed out', 'This tab was signed out successfully.', 'info');
    notifyAdminsOfActivity($pdo, $userId, 'Account signed out', 'A user signed out.', 'info');
}

echo json_encode([
    'success' => true,
    'message' => 'This tab has been signed out.',
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
