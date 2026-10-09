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