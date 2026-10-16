<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
startSecureSession();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth-check.php';
require_once __DIR__ . '/../../helpers/equipment-compatibility.php';

header('Content-Type: application/json; charset=utf-8');

function compatibilityListRespond(bool $success, string $message, array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    compatibilityListRespond(false, 'Method not allowed.', [], 405);
}

try {
    requireAuth();

    $accountRole = getAuthenticatedRole();
    if (!in_array($accountRole, ['hero', 'enemy', 'admin'], true)) {
        compatibilityListRespond(false, 'Invalid account role.', [], 403);
    }
    $userId = getAuthenticatedUserId();
    if ($userId === null) {
        compatibilityListRespond(false, 'Authentication required.', [], 401);
    }
    $equipmentAccessRoles = match ($accountRole) {
        'hero' => ['common', 'hero'],
        'enemy' => ['common', 'enemy'],
        'admin' => ['common', 'hero', 'enemy'],
    };
    $filters = [
        'user_id' => $userId,
        'assigned_role' => $accountRole,
        'access_roles' => $equipmentAccessRoles,
    ];
    $type = strtolower(trim((string) ($_GET['type'] ?? '')));
    if ($type !== '') {
        if (!in_array($type, ['compatible', 'incompatible', 'situational'], true)) {
            compatibilityListRespond(false, 'Invalid relationship type.', [], 422);
        }
        $filters['type'] = $type;
    }

    $role = strtolower(trim((string) ($_GET['role'] ?? '')));
    if ($role !== '') {
        if (!in_array($role, ['mage', 'fighter', 'marksman', 'tank', 'support'], true)) {
            compatibilityListRespond(false, 'Invalid hero role.', [], 422);
        }
        $filters['role'] = $role;
    }

    if (isset($_GET['equipment_id']) && $_GET['equipment_id'] !== '') {
        $equipmentId = filter_var($_GET['equipment_id'], FILTER_VALIDATE_INT);
        if (!$equipmentId || $equipmentId < 1) {
            compatibilityListRespond(false, 'Invalid equipment ID.', [], 422);
        }
        $filters['equipment_id'] = $equipmentId;
    }

    $records = fetchEquipmentCompatibilityRecords($pdo, $filters);
    foreach ($records as &$record) {
        $record['name'] = $record['equipment_name'] . ' + ' . $record['compatible_equipment_name'];
        $record['type'] = ucfirst($record['compatibility_type']);
        $record['description'] = $record['notes'];
    }
    unset($record);
    $rolePlaceholders = implode(', ', array_fill(0, count($equipmentAccessRoles), '?'));
    $equipmentStatement = $pdo->prepare(
        "SELECT id, name FROM equipment WHERE access_role IN ($rolePlaceholders) ORDER BY name"
    );
    $equipmentStatement->execute($equipmentAccessRoles);
    $equipment = $equipmentStatement->fetchAll(PDO::FETCH_ASSOC);

    compatibilityListRespond(true, 'Compatibility records retrieved successfully.', [
        'count' => count($records),
        'compatibility' => $records,
        'equipment' => array_map(
            static fn(array $item): array => ['id' => (int) $item['id'], 'name' => $item['name']],
            $equipment
        ),
    ]);
} catch (Throwable $e) {
    error_log('ÆTHERYX compatibility listing failed: ' . get_class($e) . ': ' . $e->getMessage());
    compatibilityListRespond(false, 'Unable to load compatibility records.', ['code' => 'SERVER_ERROR'], 500);
}
