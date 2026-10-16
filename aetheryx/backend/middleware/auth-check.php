<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    require_once __DIR__ . '/../config/session.php';
startSecureSession();
}

function requireAuth(): void
{
    global $pdo;

    require_once __DIR__ . '/../config/database.php';

    $token = (string) ($_SERVER['HTTP_X_AETHERYX_TAB_SESSION'] ?? '');
    if (!preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => 'Authentication required.',
            'code' => 'TAB_SESSION_REQUIRED',
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT s.user_id, u.full_name, u.handle, u.role, u.status
         FROM tab_auth_sessions s
         INNER JOIN users u ON u.id = s.user_id
         WHERE s.token_hash = :token_hash
           AND s.revoked_at IS NULL
           AND s.expires_at > CURRENT_TIMESTAMP
           AND s.last_seen_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 MINUTE)
         LIMIT 1'
    );
    $stmt->execute([
        ':token_hash' => hash('sha256', $token),
    ]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user || $user['status'] !== 'active') {
        if ($user && $user['status'] !== 'active') {
            revokeTabAuthSession($pdo, $token);
        }
        if (!$user) {
            $revoke = $pdo->prepare(
                'UPDATE tab_auth_sessions
                 SET revoked_at = CURRENT_TIMESTAMP
                 WHERE token_hash = :token_hash
                   AND revoked_at IS NULL
                   AND (expires_at <= CURRENT_TIMESTAMP
                        OR last_seen_at < DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 30 MINUTE))'
            );
            $revoke->execute([':token_hash' => hash('sha256', $token)]);
        }
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'success' => false,
            'message' => $user ? 'This account is inactive or no longer exists.' : 'This tab session is invalid or has expired.',
            'code' => $user ? 'ACCOUNT_INACTIVE' : 'TAB_SESSION_EXPIRED',
        ]);
        exit;
    }

    $touch = $pdo->prepare(
        'UPDATE tab_auth_sessions
         SET last_seen_at = CURRENT_TIMESTAMP
         WHERE token_hash = :token_hash AND revoked_at IS NULL'
    );
    $touch->execute([':token_hash' => hash('sha256', $token)]);

    $GLOBALS['aetheryx_authenticated_user'] = [
        'id' => (int) $user['user_id'],
        'role' => (string) $user['role'],
        'handle' => (string) ($user['handle'] ?? ''),
        'full_name' => (string) ($user['full_name'] ?? ''),
    ];
}

function createTabAuthSession(PDO $pdo, int $userId): string
{
    $token = bin2hex(random_bytes(32));
    $statement = $pdo->prepare(
        'INSERT INTO tab_auth_sessions (token_hash, user_id, last_seen_at, expires_at)
         VALUES (:token_hash, :user_id, CURRENT_TIMESTAMP, DATE_ADD(CURRENT_TIMESTAMP, INTERVAL 12 HOUR))'
    );
    $statement->execute([
        ':token_hash' => hash('sha256', $token),
        ':user_id' => $userId,
    ]);

    return $token;
}

function revokeTabAuthSession(PDO $pdo, ?string $token): void
{
    if ($token === null || !preg_match('/\A[a-f0-9]{64}\z/D', $token)) {
        return;
    }

    $statement = $pdo->prepare(
        'UPDATE tab_auth_sessions
         SET revoked_at = CURRENT_TIMESTAMP
         WHERE token_hash = :token_hash AND revoked_at IS NULL'
    );
    $statement->execute([':token_hash' => hash('sha256', $token)]);
}

function getAuthenticatedUserId(): ?int
{
    return $GLOBALS['aetheryx_authenticated_user']['id'] ?? null;
}

function getAuthenticatedRole(): ?string
{
    return $GLOBALS['aetheryx_authenticated_user']['role'] ?? null;
}
