<?php

declare(strict_types=1);

require_once __DIR__ . '/backend/config/database.php';
require_once __DIR__ . '/backend/middleware/role-check.php';

header('Content-Type: application/json');

requireAdminRole();

echo json_encode([
    'success' => true,
    'message' => 'ÆTHERYX database connection successful.',
    'database' => $dbname
]);