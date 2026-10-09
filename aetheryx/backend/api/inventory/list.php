<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

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

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    requireAuth();

    /*
    |--------------------------------------------------------------------------
    | Method
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Database
    |--------------------------------------------------------------------------
    */

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

    /*
    |--------------------------------------------------------------------------
    | Current authenticated user
    |--------------------------------------------------------------------------
    */

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
    |--------------------------------------------------------------------------
    | Inventory query
    |--------------------------------------------------------------------------
    |
    | Your known database structure:
    |
    | inventory
    | - id
    | - user_id
    | - equipment_id
    | - quantity
    | - status
    | - assigned_at
    | - returned_at
    | - notes
    |
    | equipment
    | - id
    | - name
    | - description
    | - category_id
    |
    | categories
    | - id
    | - name
    |
    */

    $sql = "
        SELECT
            i.id,
            i.user_id,
            i.equipment_id,
            i.quantity,
            i.status,
            i.assigned_at,
            i.returned_at,
            i.notes,

            e.name AS equipment_name,
            e.description AS equipment_description,

            c.name AS category_name

        FROM inventory AS i

        INNER JOIN equipment AS e
            ON e.id = i.equipment_id

        LEFT JOIN categories AS c
            ON c.id = e.category_id

        WHERE i.user_id = :user_id

        ORDER BY
            CASE
                WHEN i.status = 'assigned' THEN 1
                WHEN i.status = 'damaged' THEN 2
                WHEN i.status = 'lost' THEN 3
                WHEN i.status = 'returned' THEN 4
                ELSE 5
            END,
            i.assigned_at DESC,
            i.id DESC
    ";

    $statement = $pdo->prepare($sql);

    $statement->execute([
        ':user_id' => $userId
    ]);

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Normalize output
    |--------------------------------------------------------------------------
    */

    $inventory = [];

    foreach ($rows as $row) {

        $inventory[] = [
            'id' => (int) $row['id'],

            'user_id' => (int) $row['user_id'],

            'equipment_id' => (int) $row['equipment_id'],

            'equipment_name' =>
                (string) ($row['equipment_name'] ?? 'Unknown Equipment'),

            'name' =>
                (string) ($row['equipment_name'] ?? 'Unknown Equipment'),

            'description' =>
                (string) ($row['equipment_description'] ?? ''),

            'category' =>
                (string) ($row['category_name'] ?? 'General'),

            'category_name' =>
                (string) ($row['category_name'] ?? 'General'),

            'quantity' =>
                (int) ($row['quantity'] ?? 0),

            'status' =>
                (string) ($row['status'] ?? 'assigned'),

            'assigned_at' =>
                $row['assigned_at'] ?? null,

            'returned_at' =>
                $row['returned_at'] ?? null,

            'notes' =>
                (string) ($row['notes'] ?? '')
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Success
    |--------------------------------------------------------------------------
    */

    respond(
        true,
        'Inventory retrieved successfully.',
        [
            'user_id' => (int) $userId,
            'count' => count($inventory),
            'inventory' => $inventory
        ]
    );

} catch (PDOException $e) {

    error_log(
        'ÆTHERYX inventory/list database error: ' .
        $e->getMessage()
    );

    respond(
        false,
        'Unable to retrieve inventory.',
        [
            'code' => 'DATABASE_ERROR'
        ],
        500
    );

} catch (Throwable $e) {

    error_log(
        'ÆTHERYX inventory/list error: ' .
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
