<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth-check.php';

function jsonResponse(
    bool $success,
    string $message,
    array $extra = [],
    int $statusCode = 200
): never {
    http_response_code($statusCode);

    echo json_encode(
        array_merge(
            [
                'success' => $success,
                'message' => $message
            ],
            $extra
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
    | Request method
    |--------------------------------------------------------------------------
    */

    if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
        jsonResponse(
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
    | Database check
    |--------------------------------------------------------------------------
    */

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        jsonResponse(
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
    | Get resource records
    |--------------------------------------------------------------------------
    |
    | The API expects the resources table to contain:
    |
    | id
    | name
    | description
    | category
    |
    | If your table has additional fields, they can remain untouched.
    |
    */

    $sql = "
        SELECT
            id,
            name,
            description,
            resource_type AS category
        FROM resources
        ORDER BY id DESC
    ";

    $statement = $pdo->prepare($sql);
    $statement->execute();

    $rows = $statement->fetchAll(PDO::FETCH_ASSOC);

    /*
    |--------------------------------------------------------------------------
    | Normalize database values
    |--------------------------------------------------------------------------
    */

    $resources = [];

    foreach ($rows as $row) {

        $resources[] = [
            'id' => isset($row['id'])
                ? (int) $row['id']
                : null,

            'name' => isset($row['name'])
                ? (string) $row['name']
                : 'Unnamed Resource',

            'description' => isset($row['description'])
                ? (string) $row['description']
                : '',

            'category' => isset($row['category'])
                ? (string) $row['category']
                : 'General'
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Success response
    |--------------------------------------------------------------------------
    */

    jsonResponse(
        true,
        'Resources retrieved successfully.',
        [
            'count' => count($resources),
            'resources' => $resources
        ]
    );

} catch (PDOException $e) {

    /*
    |--------------------------------------------------------------------------
    | Database error
    |--------------------------------------------------------------------------
    |
    | Do not expose SQL/database details to the browser.
    |
    */

    error_log(
        'ÆTHERYX resources API database error: ' .
        $e->getMessage()
    );

    jsonResponse(
        false,
        'Unable to retrieve resources.',
        [
            'code' => 'DATABASE_ERROR'
        ],
        500
    );

} catch (Throwable $e) {

    /*
    |--------------------------------------------------------------------------
    | General server error
    |--------------------------------------------------------------------------
    */

    error_log(
        'ÆTHERYX resources API error: ' .
        $e->getMessage()
    );

    jsonResponse(
        false,
        'An unexpected server error occurred.',
        [
            'code' => 'SERVER_ERROR'
        ],
        500
    );
}
