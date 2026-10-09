<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth-check.php';

header('Content-Type: application/json; charset=utf-8');

function respond(bool $success, string $message, array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(false, 'Method not allowed.', ['code' => 'METHOD_NOT_ALLOWED'], 405);
}

try {
    // compatibility(equipment_id, resource_id, compatibility_type, notes)
    $stmt = $pdo->query(
        'SELECT c.id,
                c.equipment_id,
                c.resource_id,
                c.compatibility_type,
                c.notes,
                e.name AS equipment_name,
                r.name AS resource_name
         FROM compatibility AS c
         INNER JOIN equipment AS e ON e.id = c.equipment_id
         INNER JOIN resources AS r ON r.id = c.resource_id
         ORDER BY e.name ASC, r.name ASC'
    );

    $records = [];
    foreach ($stmt->fetchAll() as $row) {
        $type = (string) $row['compatibility_type'];
        $notes = trim((string) ($row['notes'] ?? ''));

        $records[] = [
            'id'          => (int) $row['id'],
            'equipment_id' => (int) $row['equipment_id'],
            'resource_id' => (int) $row['resource_id'],
            'name'         => $row['equipment_name'] . ' + ' . $row['resource_name'],
            'equipment_name' => $row['equipment_name'],
            'resource_name' => $row['resource_name'],
            'description' => $notes !== '' ? $notes : 'No additional notes.',
            'type'        => ucfirst($type),
        ];
    }

    respond(true, 'Compatibility records retrieved successfully.', [
        'count'         => count($records),
        'compatibility' => $records,
    ]);
} catch (Throwable $e) {
    error_log('ÆTHERYX compatibility/list error: ' . $e->getMessage());
    respond(false, 'Unable to load compatibility records.', ['code' => 'SERVER_ERROR'], 500);
}
