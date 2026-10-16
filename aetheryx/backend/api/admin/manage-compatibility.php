<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
startSecureSession();
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/role-check.php';
require_once __DIR__ . '/../../helpers/equipment-compatibility.php';
require_once __DIR__ . '/../../helpers/notify.php';

header('Content-Type: application/json; charset=utf-8');

function compatibilityAdminRespond(bool $success, string $message, array $data = [], int $status = 200): never
{
    http_response_code($status);
    echo json_encode(
        array_merge(['success' => $success, 'message' => $message], $data),
        JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
    );
    exit;
}

function compatibilityTextLength(string $value): int
{
    return function_exists('mb_strlen') ? mb_strlen($value, 'UTF-8') : strlen($value);
}

function normalizeCompatibilityRoles(array $data): array
{
    $roles = $data['compatible_roles'] ?? $data['roles'] ?? [];
    if (is_string($roles)) {
        $roles = array_filter(array_map('trim', explode(',', $roles)), static fn(string $role): bool => $role !== '');
    }
    if (!is_array($roles)) {
        $roles = [];
    }

    $singleRoleValue = $data['compatible_role'] ?? $data['role'] ?? $data['compatibleRole'] ?? null;
    if (is_string($singleRoleValue) && trim($singleRoleValue) !== '') {
        $roles[] = $singleRoleValue;
    }
    if (is_array($singleRoleValue)) {
        foreach ($singleRoleValue as $role) {
            if (is_string($role) && trim($role) !== '') {
                $roles[] = $role;
            }
        }
    }

    $normalized = [];
    foreach ($roles as $role) {
        if (is_string($role)) {
            $value = strtolower(trim($role));
            if ($value !== '' && $value !== 'multiple') {
                $normalized[] = $value;
            }
        }
    }

    return array_values(array_unique($normalized));
}

try {
    requireAdminRole();
    $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

    if ($method === 'GET') {
        $equipment = $pdo->query('SELECT id, name FROM equipment ORDER BY name')->fetchAll(PDO::FETCH_ASSOC);
        $users = $pdo->query(
            "SELECT id, full_name, handle, role
             FROM users
             WHERE status = 'active' AND role IN ('hero', 'enemy')
             ORDER BY full_name, handle"
        )->fetchAll(PDO::FETCH_ASSOC);
        compatibilityAdminRespond(true, 'Compatibility registry loaded.', [
            'equipment' => array_map(
                static fn(array $row): array => ['id' => (int) $row['id'], 'name' => $row['name']],
                $equipment
            ),
            'users' => array_map(
                static fn(array $row): array => [
                    'id' => (int) $row['id'],
                    'name' => (string) $row['full_name'],
                    'handle' => (string) $row['handle'],
                    'role' => (string) $row['role'],
                ],
                $users
            ),
            'records' => fetchEquipmentCompatibilityRecords($pdo),
        ]);
    }

    if (!in_array($method, ['POST', 'PUT', 'DELETE'], true)) {
        compatibilityAdminRespond(false, 'Method not allowed.', [], 405);
    }

    $data = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($data)) {
        compatibilityAdminRespond(false, 'Invalid JSON request.', [], 400);
    }

    if ($method === 'DELETE') {
        $id = filter_var($data['id'] ?? null, FILTER_VALIDATE_INT);
        if (!$id || $id < 1) {
            compatibilityAdminRespond(false, 'A valid compatibility record ID is required.', [], 422);
        }
        $assignedUserStmt = $pdo->prepare(
            'SELECT assigned_user_id FROM equipment_resource_compatibility
             WHERE id = :id AND compatible_equipment_id IS NOT NULL'
        );
        $assignedUserStmt->execute([':id' => $id]);
        $assignedUserId = (int) $assignedUserStmt->fetchColumn();
        $delete = $pdo->prepare('DELETE FROM equipment_resource_compatibility WHERE id = :id AND compatible_equipment_id IS NOT NULL');
        $delete->execute([':id' => $id]);
        if ($delete->rowCount() !== 1) {
            compatibilityAdminRespond(false, 'Compatibility record not found.', [], 404);
        }
        $adminId = (int) getAuthenticatedUserId();
        $adminMessage = sprintf('Deleted compatibility record #%d.', $id);
        if ($assignedUserId > 0) {
            createNotification(
                $pdo,
                $assignedUserId,
                'Equipment recommendation removed',
                'An administrator removed an equipment recommendation assigned to your account.',
                'info'
            );
        }
        createNotification($pdo, $adminId, 'Compatibility record deleted', $adminMessage, 'success');
        notifyAdminsOfActivity($pdo, $adminId, 'Compatibility record deleted', $adminMessage, 'info');
        compatibilityAdminRespond(true, 'Compatibility record deleted.');
    }

    $id = $method === 'PUT' ? filter_var($data['id'] ?? null, FILTER_VALIDATE_INT) : null;
    $equipmentId = filter_var($data['equipment_id'] ?? $data['first_equipment_id'] ?? null, FILTER_VALIDATE_INT);
    $compatibleEquipmentId = filter_var(
        $data['compatible_equipment_id'] ?? $data['resource_id'] ?? $data['matching_equipment_id'] ?? null,
        FILTER_VALIDATE_INT
    );
    $assignedUserId = filter_var($data['assigned_user_id'] ?? null, FILTER_VALIDATE_INT);
    $type = strtolower(trim((string) ($data['compatibility_type'] ?? $data['relationship'] ?? $data['type'] ?? '')));
    $notes = trim((string) ($data['notes'] ?? $data['reason'] ?? $data['guidance'] ?? ''));
    $situations = trim((string) ($data['situations'] ?? $data['conditions'] ?? ''));
    $roles = normalizeCompatibilityRoles($data);

    if (($method === 'PUT' && (!$id || $id < 1))
        || !$equipmentId || $equipmentId < 1
        || !$compatibleEquipmentId || $compatibleEquipmentId < 1) {
        compatibilityAdminRespond(false, 'Choose two valid equipment items.', [], 422);
    }
    if (!$assignedUserId || $assignedUserId < 1) {
        compatibilityAdminRespond(false, 'Choose the Hero or Villain account for this recommendation.', [], 422);
    }
    if ($equipmentId === $compatibleEquipmentId) {
        compatibilityAdminRespond(false, 'Choose two different equipment items.', [], 422);
    }
    if ($equipmentId > $compatibleEquipmentId) {
        [$equipmentId, $compatibleEquipmentId] = [$compatibleEquipmentId, $equipmentId];
    }
    if (!in_array($type, ['compatible', 'incompatible', 'situational'], true)) {
        compatibilityAdminRespond(false, 'Choose Compatible, Incompatible, or Situational.', [], 422);
    }
    $allowedRoles = ['mage', 'fighter', 'marksman', 'tank', 'support'];
    if ($roles === []) {
        compatibilityAdminRespond(false, 'Select at least one compatible hero role.', [], 422);
    }
    foreach ($roles as $role) {
        if (!is_string($role) || !in_array($role, $allowedRoles, true)) {
            compatibilityAdminRespond(false, 'One or more selected hero roles are invalid.', [], 422);
        }
    }
    $roles = array_values(array_unique($roles));
    if ($notes === '' || compatibilityTextLength($notes) > 5000
        || compatibilityTextLength($situations) > 5000) {
        compatibilityAdminRespond(false, 'Enter guidance (up to 5000 characters) and keep situation details under 5000 characters.', [], 422);
    }
    if ($type === 'situational' && $situations === '') {
        compatibilityAdminRespond(false, 'Explain when this situational combination may be useful.', [], 422);
    }

    $pdo->beginTransaction();

    $equipmentExists = $pdo->prepare('SELECT COUNT(*) FROM equipment WHERE id IN (:first_id, :second_id)');
    $equipmentExists->execute([
        ':first_id' => $equipmentId,
        ':second_id' => $compatibleEquipmentId,
    ]);
    if ((int) $equipmentExists->fetchColumn() !== 2) {
        throw new DomainException('The selected equipment item no longer exists.');
    }

    $assignedUserExists = $pdo->prepare(
        "SELECT id FROM users
         WHERE id = :user_id AND status = 'active' AND role IN ('hero', 'enemy')
         FOR UPDATE"
    );
    $assignedUserExists->execute([':user_id' => $assignedUserId]);
    if (!$assignedUserExists->fetchColumn()) {
        $pdo->rollBack();
        compatibilityAdminRespond(false, 'Choose an active Hero or Villain account.', [], 422);
    }

    if ($method === 'PUT') {
        $existing = $pdo->prepare(
            'SELECT id FROM equipment_resource_compatibility
             WHERE id = :id AND compatible_equipment_id IS NOT NULL
             FOR UPDATE'
        );
        $existing->execute([':id' => $id]);
        if (!$existing->fetchColumn()) {
            throw new OutOfBoundsException('Compatibility record not found.');
        }
    }

    $duplicateSql = 'SELECT id FROM equipment_resource_compatibility
                     WHERE equipment_id = :equipment_id
                       AND compatible_equipment_id = :compatible_equipment_id
                       AND assigned_user_id = :assigned_user_id';
    $duplicateParams = [
        ':equipment_id' => $equipmentId,
        ':compatible_equipment_id' => $compatibleEquipmentId,
        ':assigned_user_id' => $assignedUserId,
    ];
    if ($method === 'PUT') {
        $duplicateSql .= ' AND id <> :id';
        $duplicateParams[':id'] = $id;
    }
    $duplicate = $pdo->prepare($duplicateSql . ' LIMIT 1 FOR UPDATE');
    $duplicate->execute($duplicateParams);
    if ($duplicate->fetchColumn()) {
        throw new DomainException('That equipment pair already has a compatibility record.');
    }

    if ($method === 'POST') {
        $statement = $pdo->prepare(
            'INSERT INTO equipment_resource_compatibility
             (assigned_user_id, equipment_id, compatible_equipment_id, compatibility_type, notes, situations)
             VALUES (:assigned_user_id, :equipment_id, :compatible_equipment_id, :type, :notes, :situations)'
        );
        $statement->execute([
            ':assigned_user_id' => $assignedUserId,
            ':equipment_id' => $equipmentId,
            ':compatible_equipment_id' => $compatibleEquipmentId,
            ':type' => $type,
            ':notes' => $notes,
            ':situations' => $situations !== '' ? $situations : null,
        ]);
        $recordId = (int) $pdo->lastInsertId();
    } else {
        $statement = $pdo->prepare(
            'UPDATE equipment_resource_compatibility
             SET assigned_user_id = :assigned_user_id,
                 equipment_id = :equipment_id,
                 compatible_equipment_id = :compatible_equipment_id,
                 compatibility_type = :type,
                 notes = :notes,
                 situations = :situations
             WHERE id = :id'
        );
        $statement->execute([
            ':assigned_user_id' => $assignedUserId,
            ':equipment_id' => $equipmentId,
            ':compatible_equipment_id' => $compatibleEquipmentId,
            ':type' => $type,
            ':notes' => $notes,
            ':situations' => $situations !== '' ? $situations : null,
            ':id' => $id,
        ]);
        $recordId = $id;
        $pdo->prepare('DELETE FROM equipment_compatibility_roles WHERE compatibility_id = :id')
            ->execute([':id' => $recordId]);
    }

    $insertRole = $pdo->prepare(
        'INSERT INTO equipment_compatibility_roles (compatibility_id, compatible_role)
         VALUES (:compatibility_id, :role)'
    );
    foreach ($roles as $role) {
        $insertRole->execute([':compatibility_id' => $recordId, ':role' => $role]);
    }

    $pdo->commit();
    $adminId = (int) getAuthenticatedUserId();
    $message = $method === 'POST'
        ? 'An administrator added an equipment recommendation for your account.'
        : 'An administrator updated an equipment recommendation for your account.';
    createNotification($pdo, (int) $assignedUserId, 'Equipment recommendations updated', $message, 'info');
    $adminMessage = sprintf(
        '%s compatibility record #%d for user #%d.',
        $method === 'POST' ? 'Created' : 'Updated',
        $recordId,
        $assignedUserId
    );
    createNotification($pdo, $adminId, 'Compatibility data updated', $adminMessage, 'success');
    notifyAdminsOfActivity($pdo, $adminId, 'Compatibility data updated', $adminMessage, 'info');
    compatibilityAdminRespond(
        true,
        $method === 'POST' ? 'Compatibility record added.' : 'Compatibility record updated.',
        ['id' => $recordId],
        $method === 'POST' ? 201 : 200
    );
} catch (DomainException|OutOfBoundsException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    $status = $e instanceof OutOfBoundsException ? 404 : 409;
    compatibilityAdminRespond(false, $e->getMessage(), [], $status);
} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ÆTHERYX admin compatibility database error: ' . $e->getMessage());
    if ($e->getCode() === '23000' && (int) ($e->errorInfo[1] ?? 0) === 1062) {
        compatibilityAdminRespond(false, 'That equipment pair already has a compatibility record.', [], 409);
    }
    compatibilityAdminRespond(false, 'Unable to save compatibility data.', ['code' => 'DATABASE_ERROR'], 500);
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('ÆTHERYX admin compatibility error: ' . get_class($e) . ': ' . $e->getMessage());
    compatibilityAdminRespond(false, 'Unable to process compatibility data.', ['code' => 'SERVER_ERROR'], 500);
}
