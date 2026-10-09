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
            ':title'   => mb_substr($title, 0, 150),
            ':message' => $message,
            ':type'    => $type,
        ]);
    } catch (Throwable $e) {
        error_log('ÆTHERYX notification error: ' . $e->getMessage());
    }
}
