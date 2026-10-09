<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/role-check.php';

header('Content-Type: application/json');

requireAdminRole();

echo json_encode([
    'success' => true,
    'message' => 'Admin authorization successful.',
    'user_id' => getAuthenticatedUserId(),
    'role' => getAuthenticatedRole()
]);