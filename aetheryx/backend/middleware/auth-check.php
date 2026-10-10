<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/session.php';
startSecureSession();
}

function requireAuth(): void
{
    if (!isset($_SESSION['user_id'])) {
        http_response_code(401);

        header('Content-Type: application/json');

        echo json_encode([
            'success' => false,
            'message' => 'Authentication required.'
        ]);

        exit;
    }

    require_once __DIR__ . '/../config/database.php';

    $stmt = $pdo->prepare(
        'SELECT role, status FROM users WHERE id = :user_id LIMIT 1'
    );
    $stmt->execute([
        ':user_id' => (int) $_SESSION['user_id'],
    ]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['status'] !== 'active') {
        $_SESSION = [];
        session_destroy();

        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'This account is inactive or no longer exists.',
        ]);
        exit;
    }

    // The database is authoritative if an account role changed after login.
    $_SESSION['role'] = (string) $user['role'];
}

function getAuthenticatedUserId(): ?int
{
    if (!isset($_SESSION['user_id'])) {
        return null;
    }

    return (int) $_SESSION['user_id'];
}

function getAuthenticatedRole(): ?string
{
    if (!isset($_SESSION['role'])) {
        return null;
    }

    return (string) $_SESSION['role'];
}
