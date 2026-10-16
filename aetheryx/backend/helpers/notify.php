<?php

declare(strict_types=1);

/**
 * Create a notification for a user.
 * Never throws: a failed notification must not break the action that triggered it.
 *
 * $type: info | success | warning | danger
 */
function createNotification(PDO $pdo, int $userId, string $title, string $message, string $type = 'info'): void
{
    if (!in_array($type, ['info', 'success', 'warning', 'danger'], true)) {
        $type = 'info';
    }

    try {
        $stmt = $pdo->prepare(
            'INSERT INTO notifications (user_id, title, message, type)
             VALUES (:user_id, :title, :message, :type)'
        );
        $stmt->execute([
            ':user_id' => $userId,
            ':title'   => function_exists('mb_substr')
                ? mb_substr($title, 0, 150, 'UTF-8')
                : substr($title, 0, 150),
            ':message' => $message,
            ':type'    => $type,
        ]);
    } catch (Throwable $e) {
        error_log('ÆTHERYX notification error: ' . $e->getMessage());
    }
}

/**
 * Notify administrators about a successful account activity.
 * Notification failures are logged but never change the result of the activity.
 */
function notifyAdminsOfActivity(
    PDO $pdo,
    int $actorId,
    string $title,
    string $message,
    string $type = 'info'
): void {
    try {
        $actorStmt = $pdo->prepare('SELECT full_name, handle FROM users WHERE id = :id');
        $actorStmt->execute([':id' => $actorId]);
        $actor = $actorStmt->fetch(PDO::FETCH_ASSOC);
        $actorName = trim((string) ($actor['full_name'] ?? ''));
        if ($actorName === '') {
            $actorName = trim((string) ($actor['handle'] ?? 'A user'));
        }
        if (!empty($actor['handle'])) {
            $actorName .= ' (@' . $actor['handle'] . ')';
        }

        $adminIds = $pdo->query("SELECT id FROM users WHERE role = 'admin'")->fetchAll(PDO::FETCH_COLUMN);
        foreach ($adminIds as $adminId) {
            if ((int) $adminId === $actorId) {
                continue;
            }
            createNotification($pdo, (int) $adminId, $title, $actorName . ': ' . $message, $type);
        }
    } catch (Throwable $e) {
        error_log('ÆTHERYX activity notification error: ' . $e->getMessage());
    }
}

/**
 * Throttle repeated failed sign-in notifications per account to prevent inbox spam.
 */
function notifyFailedSignIn(PDO $pdo, int $userId, string $title, string $message): void
{
    try {
        $recent = $pdo->prepare(
            'SELECT 1 FROM notifications
             WHERE user_id = :user_id
               AND title = :title
               AND created_at >= DATE_SUB(CURRENT_TIMESTAMP, INTERVAL 15 MINUTE)
             LIMIT 1'
        );
        $recent->execute([
            ':user_id' => $userId,
            ':title' => $title,
        ]);
        if ($recent->fetchColumn()) {
            return;
        }

        createNotification($pdo, $userId, $title, $message, 'warning');
        notifyAdminsOfActivity($pdo, $userId, $title, $message, 'warning');
    } catch (Throwable $e) {
        error_log('ÆTHERYX failed sign-in notification error: ' . $e->getMessage());
    }
}
