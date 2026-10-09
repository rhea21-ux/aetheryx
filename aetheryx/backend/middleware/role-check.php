<?php

declare(strict_types=1);

require_once __DIR__ . '/auth-check.php';

function requireRole(string $requiredRole): void
{
    requireAuth();

    $currentRole = getAuthenticatedRole();

    if ($currentRole !== $requiredRole) {
        http_response_code(403);

        header('Content-Type: application/json');

        echo json_encode([
            'success' => false,
            'message' => 'Access denied.'
        ]);

        exit;
    }
}

function requireAdminRole(): void
{
    requireRole('admin');
}

function requireHeroRole(): void
{
    requireRole('hero');
}

function requireVillainRole(): void
{
    requireRole('enemy');
}

/*
|--------------------------------------------------------------------------
| ÆTHERYX SENTINEL
|--------------------------------------------------------------------------
|
| Admin-only for now (no 'staff' role exists in users.role yet).
| Isolated in its own function so promoting Sentinel to a staff-inclusive
| check later is a one-line change here, not a find-and-replace across
| every sentinel/*.php endpoint.
|
*/

function requireSentinelAccess(): void
{
    requireAdminRole();
}