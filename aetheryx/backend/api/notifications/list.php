<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, private');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth-check.php';

function respond(
    bool $success,
    string $message,
    array $data = [],
    int $status = 200
): never {

    http_response_code($status);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $data
        ),
        JSON_UNESCAPED_UNICODE |
        JSON_UNESCAPED_SLASHES
    );

    exit;
}

try {

    requireAuth();

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {

        respond(
            false,
            'Method not allowed.',
            [
                'code' => 'METHOD_NOT_ALLOWED'
            ],
            405
        );
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {

        respond(
            false,
            'Database connection is unavailable.',
            [
                'code' => 'DATABASE_UNAVAILABLE'
            ],
            500
        );
    }

    $userId = getAuthenticatedUserId();

    if (!$userId) {

        respond(
            false,
            'Authenticated user could not be identified.',
            [
                'code' => 'USER_NOT_FOUND'
            ],
            401
        );
    }

    /*
     * Only return notifications belonging to
     * the authenticated user.
     */

    $sql = "
        SELECT
            id,
            user_id,
            title,
            message,
            type,
            is_read,
            created_at
        FROM notifications
        WHERE user_id = :user_id
        ORDER BY created_at DESC, id DESC
    ";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        ':user_id' => $userId
    ]);

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    $notifications = [];

    foreach ($rows as $row) {

        $notifications[] = [
            'id' =>
                (int) $row['id'],

            'user_id' =>
                (int) $row['user_id'],

            'title' =>
                (string) ($row['title'] ?? 'System Notification'),

            'message' =>
                (string) ($row['message'] ?? ''),

            'type' =>
                (string) ($row['type'] ?? 'system'),

            'is_read' =>
                (bool) ($row['is_read'] ?? false),

            'created_at' =>
                $row['created_at'] ?? null
        ];
    }

    respond(
        true,
        'Notifications retrieved successfully.',
        [
            'user_id' => (int) $userId,
            'count' => count($notifications),
            'unread_count' => count(
                array_filter(
                    $notifications,
                    static fn(array $notification): bool =>
                        !$notification['is_read']
                )
            ),
            'notifications' => $notifications
        ]
    );

} catch (PDOException $e) {

    error_log(
        'ÆTHERYX notifications database error: ' .
        $e->getMessage()
    );

    respond(
        false,
        'Unable to retrieve notifications.',
        [
            'code' => 'DATABASE_ERROR'
        ],
        500
    );

} catch (Throwable $e) {

    error_log(
        'ÆTHERYX notifications API error: ' .
        $e->getMessage()
    );

    respond(
        false,
        'An unexpected server error occurred.',
        [
            'code' => 'SERVER_ERROR'
        ],
        500
    );
}
