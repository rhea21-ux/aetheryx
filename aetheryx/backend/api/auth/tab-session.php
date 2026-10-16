<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
startSecureSession();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth-check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

requireAuth();
$token = createTabAuthSession($pdo, (int) getAuthenticatedUserId());

echo json_encode([
    'success' => true,
    'message' => 'Independent tab session created.',
    'tab_session_token' => $token,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
