<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
startSecureSession();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth-check.php';

header('Content-Type: application/json; charset=utf-8');

function compatibilityRespond(bool $success, string $message, array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    compatibilityRespond(false, 'Method not allowed.', ['code' => 'METHOD_NOT_ALLOWED'], 405);
}

try {
    requireAuth();

    $stmt = $pdo->query(
        'SELECT c.id,
                c.equipment_id,
                c.resource_id,
                c.compatibility_type,
                c.notes,
                c.created_at,
                e.name AS equipment_name,
                r.name AS resource_name
         FROM compatibility AS c
         INNER JOIN equipment AS e ON e.id = c.equipment_id
         INNER JOIN resources AS r ON r.id = c.resource_id
         ORDER BY e.name ASC, r.name ASC'
    );

    $records = [];
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $records[] = [
            'id' => (int) $row['id'],
            'equipment_id' => (int) $row['equipment_id'],
            'resource_id' => (int) $row['resource_id'],
            'equipment_name' => (string) $row['equipment_name'],
            'resource_name' => (string) $row['resource_name'],
            'compatibility_type' => (string) $row['compatibility_type'],
            'notes' => trim((string) ($row['notes'] ?? '')),
            'created_at' => $row['created_at']
        ];
    }

    compatibilityRespond(true, 'Compatibility records retrieved successfully.', [
        'count' => count($records),
        'compatibility' => $records
    ]);
} catch (Throwable $e) {
    error_log('[ÆTHERYX] Compatibility API: ' . $e->getMessage());
    compatibilityRespond(false, 'Unable to load compatibility records.', ['code' => 'SERVER_ERROR'], 500);
}
