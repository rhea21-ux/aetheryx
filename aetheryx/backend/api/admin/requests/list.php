<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../config/session.php';
startSecureSession();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/role-check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

try {
    requireAdminRole();

    $stmt = $pdo->query(
        'SELECT
            r.id,
            r.user_id,
            r.equipment_id,
            r.quantity,
            r.status,
            r.requested_at,
            r.reviewed_at,
            r.reviewed_by,
            r.notes,

            u.full_name AS user_full_name,
            u.handle AS user_handle,
            u.email AS user_email,
            u.role AS user_role,

            e.name AS equipment_name,
            e.description AS equipment_description,
            e.available_quantity,
            e.status AS equipment_status,

            c.name AS category_name

         FROM equipment_requests r

         INNER JOIN users u
            ON u.id = r.user_id

         INNER JOIN equipment e
            ON e.id = r.equipment_id

         LEFT JOIN categories c
            ON c.id = e.category_id

         ORDER BY
            CASE
                WHEN r.status = "pending" THEN 0
                ELSE 1
            END,
            r.requested_at DESC,
            r.id DESC'
    );

    $rows = $stmt->fetchAll();

    $requests = array_map(
        static function (array $row): array {
            return [
                'id' => (int)$row['id'],

                'user' => [
                    'id' => (int)$row['user_id'],
                    'fullName' => $row['user_full_name'],
                    'handle' => $row['user_handle'],
                    'email' => $row['user_email'],
                    'role' => $row['user_role']
                ],

                'equipment' => [
                    'id' => (int)$row['equipment_id'],
                    'name' => $row['equipment_name'],
                    'description' => $row['equipment_description'],
                    'category' => $row['category_name'] ?? 'Uncategorized',
                    'availableQuantity' => (int)$row['available_quantity'],
                    'status' => $row['equipment_status']
                ],

                'quantity' => (int)$row['quantity'],
                'status' => $row['status'],
                'requestedAt' => $row['requested_at'],
                'reviewedAt' => $row['reviewed_at'],
                'reviewedBy' => $row['reviewed_by'] !== null
                    ? (int)$row['reviewed_by']
                    : null,
                'notes' => $row['notes']
            ];
        },
        $rows
    );

    echo json_encode([
        'success' => true,
        'count' => count($requests),
        'requests' => $requests
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to retrieve equipment requests.'
    ]);
} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'An unexpected server error occurred.'
    ]);
}