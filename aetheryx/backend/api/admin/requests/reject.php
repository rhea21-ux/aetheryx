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
    $notes = trim((string)($data['notes'] ?? ''));

    if ($requestId <= 0) {
        http_response_code(422);

        echo json_encode([
            'success' => false,
            'message' => 'Valid request_id is required.'
        ]);

        exit;
    }

    $stmt = $pdo->prepare(
        'SELECT id, user_id, status
         FROM equipment_requests
         WHERE id = :request_id
         LIMIT 1'
    );

    $stmt->execute([
        ':request_id' => $requestId
    ]);

    $request = $stmt->fetch();

    if (!$request) {
        http_response_code(404);

        echo json_encode([
            'success' => false,
            'message' => 'Equipment request not found.'
        ]);

        exit;
    }

    if ($request['status'] !== 'pending') {
        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' => 'Only pending requests can be rejected.'
        ]);

        exit;
    }

    $stmt = $pdo->prepare(
        'UPDATE equipment_requests
         SET status = "rejected",
             reviewed_at = CURRENT_TIMESTAMP,
             reviewed_by = :reviewed_by,
             notes = :notes
         WHERE id = :request_id
           AND status = "pending"'
    );

    $stmt->execute([
        ':reviewed_by' => $adminId,
        ':notes' => $notes !== ''
            ? $notes
            : 'Request rejected by administrator.',
        ':request_id' => $requestId
    ]);

    $rejectReason = $notes !== '' ? " Reason: {$notes}" : '';
    createNotification($pdo, (int) $request['user_id'], 'Request rejected', "Your request #{$requestId} was rejected.{$rejectReason}", 'danger');

    echo json_encode([
        'success' => true,
        'message' => 'Equipment request rejected successfully.',
        'requestId' => $requestId,
        'status' => 'rejected'
    ]);

} catch (PDOException $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to reject equipment request.'
    ]);

} catch (Throwable $e) {
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'An unexpected server error occurred.'
    ]);
}