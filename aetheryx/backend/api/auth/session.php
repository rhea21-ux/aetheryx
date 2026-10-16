<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth-check.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

requireAuth();

$userId = getAuthenticatedUserId();
$role = getAuthenticatedRole();
$authenticatedUser = $GLOBALS['aetheryx_authenticated_user'];
$handle = $authenticatedUser['handle'];
$fullName = $authenticatedUser['full_name'] ?: ($handle ?: 'User');

echo json_encode([
    'success' => true,
    'user_id' => $userId,
    'role' => $role,
    'handle' => $handle,
    'user' => [
        'id' => $userId,
        'role' => $role,
        'handle' => $handle,
        'fullName' => $fullName
    ]
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);