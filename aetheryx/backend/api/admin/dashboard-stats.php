<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../middleware/role-check.php';

header('Content-Type: application/json');

requireAdminRole();

try {

    /*
     * USERS
     */
    $stmt = $pdo->query(
        'SELECT COUNT(*) FROM users'
    );

    $userCount = (int) $stmt->fetchColumn();


    /*
     * EQUIPMENT
     */
    $stmt = $pdo->query(
        'SELECT COUNT(*) FROM equipment'
    );

    $equipmentCount = (int) $stmt->fetchColumn();


    /*
     * CATEGORIES
     */
    $stmt = $pdo->query(
        'SELECT COUNT(*) FROM categories'
    );

    $categoryCount = (int) $stmt->fetchColumn();


    /*
     * ACTIVITY LOGS
     */
    $stmt = $pdo->query(
        'SELECT COUNT(*) FROM activity_logs'
    );

    $activityCount = (int) $stmt->fetchColumn();


    /*
     * RESPONSE
     */
    echo json_encode([
        'success' => true,

        'data' => [
            'users' => $userCount,
            'equipment' => $equipmentCount,
            'categories' => $categoryCount,
            'activity_logs' => $activityCount
        ]
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Unable to load dashboard statistics.'
    ]);
}