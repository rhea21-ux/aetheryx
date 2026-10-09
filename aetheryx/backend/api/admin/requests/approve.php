<?php
declare(strict_types=1);

require_once __DIR__ . '/../../../helpers/notify.php';

require_once __DIR__ . '/../../../config/session.php';
startSecureSession();

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/role-check.php';

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);
    exit;
}

try {
    requireAdminRole();

    $adminId = getAuthenticatedUserId();

    if (!$adminId) {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Authentication required.'
        ]);

        exit;
    }

    $data = json_decode(
        file_get_contents('php://input'),
        true
    );

    if (!is_array($data)) {
        http_response_code(400);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON request.'
        ]);

        exit;
    }

    $requestId = (int)($data['request_id'] ?? 0);

    if ($requestId <= 0) {
        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'Valid request_id is required.'
        ]);

        exit;
    }

    $pdo->beginTransaction();

    /*
     * Lock the request row so two admins cannot
     * approve the same request simultaneously.
     */
    $requestStmt = $pdo->prepare(
        'SELECT
            id,
            user_id,
            equipment_id,
            quantity,
            status
         FROM equipment_requests
         WHERE id = :request_id
         FOR UPDATE'
    );

    $requestStmt->execute([
        ':request_id' => $requestId
    ]);

    $request = $requestStmt->fetch();

    if (!$request) {
        $pdo->rollBack();

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Equipment request not found.'
        ]);

        exit;
    }

    if ($request['status'] !== 'pending') {
        $pdo->rollBack();

        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' => 'Only pending requests can be approved.'
        ]);

        exit;
    }

    $userId = (int)$request['user_id'];
    $equipmentId = (int)$request['equipment_id'];
    $quantity = (int)$request['quantity'];

    /*
     * Lock the equipment row and check stock again.
     */
    $equipmentStmt = $pdo->prepare(
        'SELECT
            id,
            name,
            available_quantity,
            status
         FROM equipment
         WHERE id = :equipment_id
         FOR UPDATE'
    );

    $equipmentStmt->execute([
        ':equipment_id' => $equipmentId
    ]);

    $equipment = $equipmentStmt->fetch();

    if (!$equipment) {
        $pdo->rollBack();

        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Equipment no longer exists.'
        ]);

        exit;
    }

    if ($equipment['status'] !== 'available') {
        $pdo->rollBack();

        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' => 'Equipment is no longer available.'
        ]);

        exit;
    }

    $availableQuantity = (int)$equipment['available_quantity'];

    if ($quantity > $availableQuantity) {
        $pdo->rollBack();

        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' => 'Not enough equipment available.',
            'availableQuantity' => $availableQuantity
        ]);

        exit;
    }

    /*
     * Create inventory assignment.
     */
    $inventoryStmt = $pdo->prepare(
        'INSERT INTO inventory
            (
                user_id,
                equipment_id,
                quantity,
                status,
                notes
            )
         VALUES
            (
                :user_id,
                :equipment_id,
                :quantity,
                "assigned",
                :notes
            )'
    );

    $inventoryStmt->execute([
        ':user_id' => $userId,
        ':equipment_id' => $equipmentId,
        ':quantity' => $quantity,
        ':notes' => 'Assigned from approved equipment request #' . $requestId
    ]);

    $inventoryId = (int)$pdo->lastInsertId();

    /*
     * Reduce available stock.
     */
    $newAvailableQuantity =
        $availableQuantity - $quantity;

    $equipmentUpdateStmt = $pdo->prepare(
        'UPDATE equipment
         SET available_quantity = :available_quantity,
             status = CASE
                 WHEN :new_quantity = 0
                 THEN "unavailable"
                 ELSE status
             END
         WHERE id = :equipment_id'
    );

    $equipmentUpdateStmt->execute([
        ':available_quantity' => $newAvailableQuantity,
        ':new_quantity' => $newAvailableQuantity,
        ':equipment_id' => $equipmentId
    ]);

    /*
     * Mark request as approved.
     */
    $requestUpdateStmt = $pdo->prepare(
        'UPDATE equipment_requests
         SET status = "approved",
             reviewed_at = CURRENT_TIMESTAMP,
             reviewed_by = :reviewed_by
         WHERE id = :request_id'
    );

    $requestUpdateStmt->execute([
        ':reviewed_by' => $adminId,
        ':request_id' => $requestId
    ]);

    /*
     * Remove the requested quantity from the user's cart.
     *
     * If the cart has more than the requested quantity,
     * reduce the cart quantity instead of deleting the row.
     */
    $cartStmt = $pdo->prepare(
        'SELECT id, quantity
         FROM cart_items
         WHERE user_id = :user_id
           AND equipment_id = :equipment_id
         LIMIT 1
         FOR UPDATE'
    );

    $cartStmt->execute([
        ':user_id' => $userId,
        ':equipment_id' => $equipmentId
    ]);

    $cartItem = $cartStmt->fetch();

    if ($cartItem) {
        $cartQuantity = (int)$cartItem['quantity'];

        if ($cartQuantity <= $quantity) {
            $deleteCartStmt = $pdo->prepare(
                'DELETE FROM cart_items
                 WHERE id = :id'
            );

            $deleteCartStmt->execute([
                ':id' => (int)$cartItem['id']
            ]);
        } else {
            $remainingCartQuantity =
                $cartQuantity - $quantity;

            $updateCartStmt = $pdo->prepare(
                'UPDATE cart_items
                 SET quantity = :quantity
                 WHERE id = :id'
            );

            $updateCartStmt->execute([
                ':quantity' => $remainingCartQuantity,
                ':id' => (int)$cartItem['id']
            ]);
        }
    }

    createNotification($pdo, $userId, 'Request approved', "Your request #{$requestId} was approved. The equipment is now in your inventory.", 'success');

    $pdo->commit();

    echo json_encode([
        'success' => true,
        'message' => 'Equipment request approved successfully.',
        'requestId' => $requestId,
        'inventoryId' => $inventoryId,
        'equipmentId' => $equipmentId,
        'quantity' => $quantity,
        'remainingAvailableQuantity' => $newAvailableQuantity
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to approve equipment request.'
    ]);

} catch (Throwable $e) {

    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'An unexpected server error occurred.'
    ]);
}