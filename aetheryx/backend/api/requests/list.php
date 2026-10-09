<?php
declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
startSecureSession();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth-check.php';

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
    requireAuth();

    $userId = getAuthenticatedUserId();
    $role = getAuthenticatedRole();

    if (!$userId || !in_array($role, ['hero', 'enemy'], true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Only Hero and Villain accounts can view equipment requests.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT
            r.id,
            r.equipment_id,
            r.quantity,
            r.status,
            r.requested_at,
            r.reviewed_at,
            r.notes,
            e.name AS equipment_name,
            e.description AS equipment_description,
            e.access_role,
            c.name AS category_name
         FROM equipment_requests r
         INNER JOIN equipment e
             ON e.id = r.equipment_id
         LEFT JOIN categories c
             ON c.id = e.category_id
         WHERE r.user_id = :user_id
         ORDER BY r.requested_at DESC, r.id DESC'
    );

    $stmt->execute([
        ':user_id' => $userId
    ]);

    $rows = $stmt->fetchAll();

    $requests = array_map(
        static function (array $row): array {
            return [
                'id' => (int)$row['id'],
                'equipmentId' => (int)$row['equipment_id'],
                'equipmentName' => $row['equipment_name'],
                'equipmentDescription' => $row['equipment_description'],
                'category' => $row['category_name'] ?? 'Uncategorized',
                'accessRole' => $row['access_role'],
                'quantity' => (int)$row['quantity'],
                'status' => $row['status'],
                'requestedAt' => $row['requested_at'],
                'reviewedAt' => $row['reviewed_at'],
                'notes' => $row['notes']
            ];
        },
        $rows
    );

    echo json_encode([
        'success' => true,
        'role' => $role,
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