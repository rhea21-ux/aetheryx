<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../../config/session.php';
startSecureSession();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth-check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.',
        'code' => 'METHOD_NOT_ALLOWED'
    ]);

    exit;
}

try {

    requireAuth();

    $userId = getAuthenticatedUserId();

    if (!$userId) {

        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Authentication required.',
            'code' => 'AUTH_REQUIRED'
        ]);

        exit;
    }

    $stmt = $pdo->prepare("
        SELECT
            id,
            full_name,
            handle,
            email,
            role,
            status,
            created_at,
            updated_at
        FROM users
        WHERE id = :id
        LIMIT 1
    ");

    $stmt->execute([
        ':id' => $userId
    ]);

    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'User account was not found.',
            'code' => 'USER_NOT_FOUND'
        ]);

        exit;
    }

    echo json_encode([
        'success' => true,
        'message' => 'Profile retrieved successfully.',
        'user' => [
            'id' => (int) $user['id'],

            'fullName' =>
                $user['full_name'],

            'handle' =>
                $user['handle'],

            'email' =>
                $user['email'],

            'role' =>
                $user['role'],

            'status' =>
                $user['status'],

            'createdAt' =>
                $user['created_at'],

            'updatedAt' =>
                $user['updated_at']
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {

    error_log(
        '[ÆTHERYX][PROFILE] ' .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database error while loading profile.',
        'code' => 'DATABASE_ERROR'
    ]);

} catch (Throwable $e) {

    error_log(
        '[ÆTHERYX][PROFILE] ' .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unexpected server error.',
        'code' => 'SERVER_ERROR'
    ]);
}