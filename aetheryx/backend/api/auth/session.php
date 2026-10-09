<?php

declare(strict_types=1);

require_once __DIR__ . '/../../middleware/auth-check.php';

header('Content-Type: application/json');

requireAuth();

echo json_encode([
    'success' => true,
    'user_id' => getAuthenticatedUserId(),
    'role' => getAuthenticatedRole(),
    'handle' => $_SESSION['handle'] ?? null
]);