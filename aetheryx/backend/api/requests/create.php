<?php
declare(strict_types=1);

require_once __DIR__ . '/../../helpers/notify.php';

require_once __DIR__ . '/../../config/session.php';
startSecureSession();

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/auth-check.php';

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
    requireAuth();

    $userId = getAuthenticatedUserId();
    $role = getAuthenticatedRole();

    if (!$userId || !in_array($role, ['hero', 'enemy'], true)) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'Only Hero and Villain accounts can request equipment.'
        ]);
        exit;
    }

    $data = json_decode(file_get_contents('php://input'), true);

    if (!is_array($data)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid JSON request.'
        ]);
        exit;
    }

    $equipmentId = (int)($data['equipment_id'] ?? 0);
    $quantity = (int)($data['quantity'] ?? 1);

    if ($equipmentId <= 0 || $quantity <= 0) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Valid equipment_id and quantity are required.'
        ]);
        exit;
    }

    /*
     * Verify that the equipment exists and that this user's
     * role is allowed to access it.
     */
    $equipmentStmt = $pdo->prepare(
        'SELECT id, name, access_role, available_quantity, status
         FROM equipment
         WHERE id = :equipment_id
         LIMIT 1'
    );

    $equipmentStmt->execute([
        ':equipment_id' => $equipmentId
    ]);

    $equipment = $equipmentStmt->fetch();

    if (!$equipment) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Equipment not found.'
        ]);
        exit;
    }

    $accessRole = (string)$equipment['access_role'];

    $allowed = (
        $accessRole === 'common' ||
        ($role === 'hero' && $accessRole === 'hero') ||
        ($role === 'enemy' && $accessRole === 'enemy')
    );

    if (!$allowed) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'message' => 'You are not authorized to request this equipment.'
        ]);
        exit;
    }

    if ($equipment['status'] !== 'available') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'This equipment is currently unavailable.'
        ]);
        exit;
    }

    $availableQuantity = (int)$equipment['available_quantity'];

    if ($quantity > $availableQuantity) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Requested quantity exceeds available stock.',
            'available_quantity' => $availableQuantity
        ]);
        exit;
    }

    /*
     * Prevent duplicate pending requests for the same
     * user and equipment.
     */
    $existingStmt = $pdo->prepare(
        'SELECT id, quantity
         FROM equipment_requests
         WHERE user_id = :user_id
           AND equipment_id = :equipment_id
           AND status = "pending"
         LIMIT 1'
    );

    $existingStmt->execute([
        ':user_id' => $userId,
        ':equipment_id' => $equipmentId
    ]);

    $existing = $existingStmt->fetch();

    if ($existing) {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'You already have a pending request for this equipment.',
            'request_id' => (int)$existing['id']
        ]);
        exit;
    }

    $notes = trim((string)($data['notes'] ?? ''));

    $insertStmt = $pdo->prepare(
        'INSERT INTO equipment_requests
            (user_id, equipment_id, quantity, status, notes)
         VALUES
            (:user_id, :equipment_id, :quantity, "pending", :notes)'
    );

    $insertStmt->execute([
        ':user_id' => $userId,
        ':equipment_id' => $equipmentId,
        ':quantity' => $quantity,
        ':notes' => $notes !== '' ? $notes : null
    ]);

    $requestId = (int)$pdo->lastInsertId();

    createNotification($pdo, (int) $userId, 'Request submitted', "Your request #{$requestId} for {$equipment['name']} is waiting for admin review.", 'info');

    echo json_encode([
        'success' => true,
        'message' => 'Equipment request submitted successfully.',
        'request' => [
            'id' => $requestId,
            'equipmentId' => $equipmentId,
            'equipmentName' => $equipment['name'],
            'quantity' => $quantity,
            'status' => 'pending'
        ]
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to create equipment request.'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected server error occurred.'
    ]);
}