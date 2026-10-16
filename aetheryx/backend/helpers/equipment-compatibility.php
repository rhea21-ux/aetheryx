<?php

declare(strict_types=1);

function fetchEquipmentCompatibilityRecords(PDO $pdo, array $filters = []): array
{
    $where = ['c.compatible_equipment_id IS NOT NULL'];
    $parameters = [];

    if (isset($filters['type'])) {
        $where[] = 'c.compatibility_type = :type';
        $parameters[':type'] = $filters['type'];
    }
    if (isset($filters['role'])) {
        $where[] = 'EXISTS (
            SELECT 1 FROM equipment_compatibility_roles filtered_role
            WHERE filtered_role.compatibility_id = c.id
              AND filtered_role.compatible_role = :role
        )';
        $parameters[':role'] = $filters['role'];
    }
    if (isset($filters['equipment_id'])) {
        $where[] = '(c.equipment_id = :equipment_id OR c.compatible_equipment_id = :equipment_id_other)';
        $parameters[':equipment_id'] = $filters['equipment_id'];
        $parameters[':equipment_id_other'] = $filters['equipment_id'];
    }
    if (isset($filters['user_id'])) {
        // assigned_role column does not exist — filter by assigned_user_id only
        $where[] = 'c.assigned_user_id = :user_id';
        $parameters[':user_id'] = $filters['user_id'];
    }
    if (isset($filters['access_roles'])) {
        $firstRolePlaceholders  = [];
        $secondRolePlaceholders = [];
        foreach (array_values($filters['access_roles']) as $index => $role) {
            $firstPlaceholder  = ':first_access_role_'  . $index;
            $secondPlaceholder = ':second_access_role_' . $index;
            $firstRolePlaceholders[]  = $firstPlaceholder;
            $secondRolePlaceholders[] = $secondPlaceholder;
            $parameters[$firstPlaceholder]  = $role;
            $parameters[$secondPlaceholder] = $role;
        }
        if ($firstRolePlaceholders !== []) {
            $where[] = 'first_equipment.access_role IN ('  . implode(', ', $firstRolePlaceholders)  . ')';
            $where[] = 'second_equipment.access_role IN (' . implode(', ', $secondRolePlaceholders) . ')';
        }
    }

    $sql = '
        SELECT c.id, c.assigned_user_id, c.equipment_id, c.compatible_equipment_id,
               c.compatibility_type, c.notes, c.situations, c.created_at, c.updated_at,
               first_equipment.name  AS equipment_name,
               second_equipment.name AS compatible_equipment_name,
               assigned_user.full_name AS assigned_user_name,
               assigned_user.handle    AS assigned_user_handle,
               assigned_user.role      AS assigned_user_role,
               GROUP_CONCAT(DISTINCT roles.compatible_role ORDER BY roles.compatible_role SEPARATOR ",") AS compatible_roles
        FROM equipment_resource_compatibility c
        INNER JOIN equipment first_equipment  ON first_equipment.id  = c.equipment_id
        INNER JOIN equipment second_equipment ON second_equipment.id = c.compatible_equipment_id
        LEFT  JOIN users assigned_user        ON assigned_user.id    = c.assigned_user_id
        LEFT  JOIN equipment_compatibility_roles roles ON roles.compatibility_id = c.id
        WHERE ' . implode(' AND ', $where) . '
        GROUP BY c.id
        ORDER BY first_equipment.name, second_equipment.name, c.id
    ';

    $statement = $pdo->prepare($sql);
    $statement->execute($parameters);
    $records = [];

    foreach ($statement->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $records[] = [
            'id'                        => (int) $row['id'],
            'assigned_user_id'          => $row['assigned_user_id'] === null ? null : (int) $row['assigned_user_id'],
            'assigned_user_name'        => (string) ($row['assigned_user_name']   ?? ''),
            'assigned_user_handle'      => (string) ($row['assigned_user_handle'] ?? ''),
            'assigned_user_role'        => (string) ($row['assigned_user_role']   ?? ''),
            'equipment_id'              => (int) $row['equipment_id'],
            'compatible_equipment_id'   => (int) $row['compatible_equipment_id'],
            'equipment_name'            => (string) $row['equipment_name'],
            'compatible_equipment_name' => (string) $row['compatible_equipment_name'],
            'compatibility_type'        => (string) $row['compatibility_type'],
            'compatible_roles'          => ($row['compatible_roles'] === null || $row['compatible_roles'] === '')
                                            ? []
                                            : explode(',', (string) $row['compatible_roles']),
            'notes'      => (string) ($row['notes']      ?? ''),
            'situations' => (string) ($row['situations'] ?? ''),
            'created_at' => $row['created_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    return $records;
}
