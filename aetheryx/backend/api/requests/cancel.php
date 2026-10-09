<?php
declare(strict_types=1);

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
            'message' => 'Only Hero and Villain accounts can cancel requests.'
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

    $requestId = (int)($data['request_id'] ?? 0);

    if ($requestId <= 0) {
        http_response_code(422);
        echo json_encode([
            'success' => false,
            'message' => 'Valid request_id is required.'
        ]);
        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT id, status
         FROM equipment_requests
         WHERE id = :request_id
           AND user_id = :user_id
         LIMIT 1'
    );

    $stmt->execute([
        ':request_id' => $requestId,
        ':user_id' => $userId
    ]);

    $request = $stmt->fetch();

    if (!$request) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'message' => 'Request not found.'
        ]);
        exit;
    }

    if ($request['status'] !== 'pending') {
        http_response_code(409);
        echo json_encode([
            'success' => false,
            'message' => 'Only pending requests can be cancelled.'
        ]);
        exit;
    }

    $updateStmt = $pdo->prepare(
        'UPDATE equipment_requests
         SET status = "cancelled",
             reviewed_at = CURRENT_TIMESTAMP
         WHERE id = :request_id
           AND user_id = :user_id
           AND status = "pending"'
    );

    $updateStmt->execute([
        ':request_id' => $requestId,
        ':user_id' => $userId
    ]);

    echo json_encode([
        'success' => true,
        'message' => 'Equipment request cancelled successfully.',
        'request_id' => $requestId,
        'status' => 'cancelled'
    ]);

} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Unable to cancel equipment request.'
    ]);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'An unexpected server error occurred.'
    ]);
}   