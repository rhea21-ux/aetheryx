<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ÆTHERYX Shared JSON Response Helper
|--------------------------------------------------------------------------
|
| Extracted from the respond() pattern already duplicated in
| inventory/list.php, compatibility/list.php, notifications/list.php,
| and resources/list.php. Existing endpoints are untouched; new
| Sentinel endpoints use this instead of redefining respond() locally.
|
*/

if (!function_exists('respond')) {

    function respond(
        bool $success,
        string $message,
        array $data = [],
        int $status = 200
    ): never {

        http_response_code($status);

        header('Content-Type: application/json; charset=utf-8');

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
}
