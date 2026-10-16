<?php

declare(strict_types=1);

require_once __DIR__ . '/../../../config/session.php';
startSecureSession();
require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/role-check.php';
require_once __DIR__ . '/../../../helpers/notify.php';

header('Content-Type: application/json; charset=utf-8');
requireAdminRole();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    try {
        $stmt = $pdo->query(
            'SELECT id, name, access_role, price, status
             FROM equipment ORDER BY name, id'
        );
        $items = array_map(static function (array $item): array {
            return [
                'id' => (int)$item['id'],
                'name' => $item['name'],
                'access_role' => $item['access_role'],
                'price' => (float)$item['price'],
                'status' => $item['status'],
            ];
        }, $stmt->fetchAll());

        echo json_encode(['success' => true, 'equipment' => $items]);
    } catch (Throwable $e) {
        error_log('ÆTHERYX equipment price listing failed: ' . get_class($e) . ': ' . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'message' => 'Unable to retrieve equipment prices.']);
    }
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed.']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON request.']);
    exit;
}

$equipmentId = filter_var($data['equipment_id'] ?? null, FILTER_VALIDATE_INT);
$priceInput = $data['price'] ?? null;
if (!$equipmentId || $equipmentId < 1 || !is_scalar($priceInput)
    || !preg_match('/^\d{1,8}(?:\.\d{1,2})?$/', trim((string)$priceInput))) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'A valid equipment ID and price are required.']);
    exit;
}

$price = (float)$priceInput;
if (!is_finite($price) || $price < 0 || $price > 99999999.99) {
    http_response_code(422);
    echo json_encode(['success' => false, 'message' => 'Price must be between 0.00 and 99,999,999.99.']);
    exit;
}

try {
    $stmt = $pdo->prepare('UPDATE equipment SET price = :price WHERE id = :equipment_id');
    $stmt->execute([
        ':price' => number_format($price, 2, '.', ''),
        ':equipment_id' => $equipmentId,
    ]);

    if ($stmt->rowCount() === 0) {
        $exists = $pdo->prepare('SELECT 1 FROM equipment WHERE id = :equipment_id');
        $exists->execute([':equipment_id' => $equipmentId]);
        if (!$exists->fetchColumn()) {
            http_response_code(404);
            echo json_encode(['success' => false, 'message' => 'Equipment not found.']);
            exit;
        }
    }

    $adminId = (int) getAuthenticatedUserId();
    $message = sprintf('Set the price for equipment #%d to PHP %s.', $equipmentId, number_format($price, 2, '.', ','));
    createNotification($pdo, $adminId, 'Equipment price updated', $message, 'success');
    notifyAdminsOfActivity($pdo, $adminId, 'Equipment price updated', $message, 'info');

    echo json_encode([
        'success' => true,
        'message' => 'Equipment price saved.',
        'equipment_id' => (int)$equipmentId,
        'price' => number_format($price, 2, '.', ''),
    ]);
} catch (Throwable $e) {
    error_log('ÆTHERYX equipment price update failed: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Unable to save the equipment price.']);
}
