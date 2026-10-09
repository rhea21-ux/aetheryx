<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/role-check.php';

header('Content-Type: application/json');

requireAdminRole();

$method = $_SERVER['REQUEST_METHOD'];

try {

    /*
    |--------------------------------------------------------------------------
    | GET — LIST USERS
    |--------------------------------------------------------------------------
    */

    if ($method === 'GET') {

        $stmt = $pdo->query("
            SELECT
                id,
                full_name,
                handle,
                email,
                role,
                status,
                created_at,
                updated_at
            FROM users
            ORDER BY id DESC
        ");

        $users = $stmt->fetchAll();

        echo json_encode([
            'success' => true,
            'data' => $users
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | PUT — UPDATE USER ROLE / STATUS
    |--------------------------------------------------------------------------
    */

    if ($method === 'PUT') {

        $input = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($input)) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Invalid JSON request.'
            ]);

            exit;
        }

        $userId = isset($input['id'])
            ? (int) $input['id']
            : 0;

        if ($userId <= 0) {
            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Valid user ID is required.'
            ]);

            exit;
        }

        /*
        |--------------------------------------------------------------------------
        | PREVENT ADMIN FROM MODIFYING THEMSELVES
        |--------------------------------------------------------------------------
        */

        if ($userId === getAuthenticatedUserId()) {
            http_response_code(403);

            echo json_encode([
                'success' => false,
                'message' => 'You cannot modify your own administrator account.'
            ]);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE ROLE
        |--------------------------------------------------------------------------
        */

        $allowedRoles = [
            'hero',
            'enemy'
        ];

        $role = isset($input['role'])
            ? trim((string) $input['role'])
            : null;

        // The UI label is "Villain"; the stored role is "enemy".
        if ($role === 'villain') {
            $role = 'enemy';
        }

        if ($role !== null && !in_array($role, $allowedRoles, true)) {

            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Invalid role.'
            ]);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | VALIDATE STATUS
        |--------------------------------------------------------------------------
        */

        $allowedStatuses = [
            'active',
            'suspended',
            'inactive'
        ];

        $status = isset($input['status'])
            ? trim((string) $input['status'])
            : null;

        if (
            $status !== null &&
            !in_array($status, $allowedStatuses, true)
        ) {

            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Invalid status.'
            ]);

            exit;
        }


        if ($role === null && $status === null) {

            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Nothing to update.'
            ]);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK USER EXISTS
        |--------------------------------------------------------------------------
        */

        $check = $pdo->prepare("
            SELECT id, role, status
            FROM users
            WHERE id = :id
            LIMIT 1
        ");

        $check->execute([
            ':id' => $userId
        ]);

        $existingUser = $check->fetch();

        if (!$existingUser) {

            http_response_code(404);

            echo json_encode([
                'success' => false,
                'message' => 'User not found.'
            ]);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | BUILD UPDATE QUERY
        |--------------------------------------------------------------------------
        */

        $updates = [];
        $params = [
            ':id' => $userId
        ];

        if ($role !== null) {
            $updates[] = 'role = :role';
            $params[':role'] = $role;
        }

        if ($status !== null) {
            $updates[] = 'status = :status';
            $params[':status'] = $status;
        }

        $updates[] = 'updated_at = CURRENT_TIMESTAMP';


        $sql = "
            UPDATE users
            SET " . implode(', ', $updates) . "
            WHERE id = :id
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);


        /*
        |--------------------------------------------------------------------------
        | ACTIVITY LOG
        |--------------------------------------------------------------------------
        */

        $adminId = getAuthenticatedUserId();

        $changes = [];

        if ($role !== null) {
            $changes[] = 'role changed to ' . $role;
        }

        if ($status !== null) {
            $changes[] = 'status changed to ' . $status;
        }

        $description = 'Admin updated user #' . $userId . ': '
            . implode(', ', $changes);

        $log = $pdo->prepare("
            INSERT INTO activity_logs
            (
                user_id,
                action,
                description,
                entity_type,
                entity_id,
                ip_address,
                user_agent
            )
            VALUES
            (
                :user_id,
                :action,
                :description,
                :entity_type,
                :entity_id,
                :ip_address,
                :user_agent
            )
        ");

        $log->execute([
            ':user_id' => $adminId,
            ':action' => 'USER_UPDATED',
            ':description' => $description,
            ':entity_type' => 'user',
            ':entity_id' => $userId,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);


        echo json_encode([
            'success' => true,
            'message' => 'User updated successfully.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | DELETE — DELETE USER
    |--------------------------------------------------------------------------
    */

    if ($method === 'DELETE') {

        $input = json_decode(
            file_get_contents('php://input'),
            true
        );

        if (!is_array($input)) {
            $input = $_GET;
        }

        $userId = isset($input['id'])
            ? (int) $input['id']
            : 0;


        if ($userId <= 0) {

            http_response_code(400);

            echo json_encode([
                'success' => false,
                'message' => 'Valid user ID is required.'
            ]);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | PREVENT SELF-DELETION
        |--------------------------------------------------------------------------
        */

        if ($userId === getAuthenticatedUserId()) {

            http_response_code(403);

            echo json_encode([
                'success' => false,
                'message' => 'You cannot delete your own administrator account.'
            ]);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | CHECK USER
        |--------------------------------------------------------------------------
        */

        $check = $pdo->prepare("
            SELECT id, full_name, handle
            FROM users
            WHERE id = :id
            LIMIT 1
        ");

        $check->execute([
            ':id' => $userId
        ]);

        $user = $check->fetch();

        if (!$user) {

            http_response_code(404);

            echo json_encode([
                'success' => false,
                'message' => 'User not found.'
            ]);

            exit;
        }


        /*
        |--------------------------------------------------------------------------
        | DELETE USER
        |--------------------------------------------------------------------------
        |
        | Because other tables may reference this user, first remove
        | dependent records that are safe to remove.
        |
        */

        $pdo->beginTransaction();

        try {

            $stmt = $pdo->prepare("
                DELETE FROM notifications
                WHERE user_id = :user_id
            ");

            $stmt->execute([
                ':user_id' => $userId
            ]);


            $stmt = $pdo->prepare("
                UPDATE activity_logs
                SET user_id = NULL
                WHERE user_id = :user_id
            ");

            $stmt->execute([
                ':user_id' => $userId
            ]);


            $stmt = $pdo->prepare("
                DELETE FROM inventory
                WHERE user_id = :user_id
            ");

            $stmt->execute([
                ':user_id' => $userId
            ]);


            $stmt = $pdo->prepare("
                DELETE FROM users
                WHERE id = :id
            ");

            $stmt->execute([
                ':id' => $userId
            ]);

            $pdo->commit();

        } catch (Throwable $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            throw $e;
        }


        /*
        |--------------------------------------------------------------------------
        | LOG DELETION
        |--------------------------------------------------------------------------
        */

        $adminId = getAuthenticatedUserId();

        $log = $pdo->prepare("
            INSERT INTO activity_logs
            (
                user_id,
                action,
                description,
                entity_type,
                entity_id,
                ip_address,
                user_agent
            )
            VALUES
            (
                :user_id,
                :action,
                :description,
                :entity_type,
                :entity_id,
                :ip_address,
                :user_agent
            )
        ");

        $log->execute([
            ':user_id' => $adminId,
            ':action' => 'USER_DELETED',
            ':description' =>
                'Admin deleted user #' . $userId .
                ' (' . $user['handle'] . ')',
            ':entity_type' => 'user',
            ':entity_id' => $userId,
            ':ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            ':user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);


        echo json_encode([
            'success' => true,
            'message' => 'User deleted successfully.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | METHOD NOT ALLOWED
    |--------------------------------------------------------------------------
    */

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Database operation failed.'
    ]);
}