<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/middleware/auth-check.php';

header('Content-Type: application/json');

requireAuth();

echo json_encode([
    'success' => true,
    'message' => 'Authenticated session is active.',
    'user_id' => getAuthenticatedUserId(),
    'role' => getAuthenticatedRole()
]);