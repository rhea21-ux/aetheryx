<?php

declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../../config/database.php';
require_once __DIR__ . '/../../../middleware/auth-check.php';
require_once __DIR__ . '/../../../helpers/response.php';
require_once __DIR__ . '/../../../helpers/sentinel-intelligence.php';

header('Content-Type: application/json; charset=utf-8');

requireAuth();

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    respond(false, 'Method not allowed.', ['code' => 'METHOD_NOT_ALLOWED'], 405);
}

$role = getAuthenticatedRole();
$userId = getAuthenticatedUserId();
$isAdmin = ($role === 'admin');

try {

    $sql = "
        SELECT
            mr.id, mr.equipment_id, mr.requester_id, mr.assigned_to,
            mr.description, mr.reported_severity, mr.status,
            mr.resolution_notes, mr.created_at, mr.updated_at, mr.resolved_at,
            e.name AS equipment_name, e.status AS equipment_status,
            req.full_name AS requester_name,
            asg.full_name AS assigned_name
        FROM maintenance_requests mr
        LEFT JOIN equipment e ON e.id = mr.equipment_id
        LEFT JOIN users req ON req.id = mr.requester_id
        LEFT JOIN users asg ON asg.id = mr.assigned_to
    ";

    $params = [];

    // Non-admins can only ever see their own requests — enforced server-side,
    // regardless of what the client sends.
    if (!$isAdmin) {
        $sql .= " WHERE mr.requester_id = :requester_id";
        $params[':requester_id'] = $userId;
    }

    $sql .= " ORDER BY mr.created_at DESC";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $requests = $stmt->fetchAll();

    // Open-request counts per equipment, for the "other open requests" priority factor.
    $openByEquip = [];
    $stmt = $pdo->query("
        SELECT equipment_id, COUNT(*) AS cnt
        FROM maintenance_requests
        WHERE status IN ('open', 'assigned', 'in_progress')
        GROUP BY equipment_id
    ");
    foreach ($stmt->fetchAll() as $row) {
        $openByEquip[(int) $row['equipment_id']] = (int) $row['cnt'];
    }

    $items = array_map(static function (array $req) use ($openByEquip): array {

        $priority = null;

        if (in_array($req['status'], ['open', 'assigned', 'in_progress'], true)) {
            $ageDays = (int) floor((time() - strtotime($req['created_at'])) / 86400);
            $otherOpen = max(0, ($openByEquip[(int) $req['equipment_id']] ?? 1) - 1);

            $priority = sentinelMaintenancePriority(
                $req['reported_severity'],
                $req['equipment_status'] ?? 'available',
                $ageDays,
                $otherOpen
            );
        }

        return [
            'id' => (int) $req['id'],
            'equipmentId' => (int) $req['equipment_id'],
            'equipmentName' => $req['equipment_name'],
            'requesterId' => (int) $req['requester_id'],
            'requesterName' => $req['requester_name'],
            'assignedTo' => $req['assigned_to'] !== null ? (int) $req['assigned_to'] : null,
            'assignedName' => $req['assigned_name'],
            'description' => $req['description'],
            'reportedSeverity' => $req['reported_severity'],
            'status' => $req['status'],
            'resolutionNotes' => $req['resolution_notes'],
            'createdAt' => $req['created_at'],
            'updatedAt' => $req['updated_at'],
            'resolvedAt' => $req['resolved_at'],
            'priority' => $priority
        ];
    }, $requests);

    respond(true, 'Maintenance requests retrieved successfully.', [
        'count' => count($items),
        'requests' => $items
    ]);

} catch (PDOException $e) {

    error_log('ÆTHERYX SENTINEL maintenance/list PDO error: ' . $e->getMessage());

    respond(false, 'Unable to retrieve maintenance requests.', ['code' => 'DATABASE_ERROR'], 500);

} catch (Throwable $e) {

    error_log('ÆTHERYX SENTINEL maintenance/list error: ' . $e->getMessage());

    respond(false, 'An unexpected server error occurred.', ['code' => 'SERVER_ERROR'], 500);
}
