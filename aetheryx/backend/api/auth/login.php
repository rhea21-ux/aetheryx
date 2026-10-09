<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/session.php';
startSecureSession();

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
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

$identifier = trim((string)($data['identifier'] ?? ''));
$password   = (string)($data['password'] ?? '');

if ($identifier === '' || $password === '') {
    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Identifier and password are required.'
    ]);

    exit;
}

try {

    /*
    |--------------------------------------------------------------------------
    | Find user by email OR handle
    |--------------------------------------------------------------------------
    */

$stmt = $pdo->prepare(
    'SELECT
        id,
        full_name,
        handle,
        email,
        password_hash,
        role,
        status
     FROM users
     WHERE email = :email
        OR handle = :handle
     LIMIT 1'
);

$stmt->execute([
    ':email'  => $identifier,
    ':handle' => $identifier
]);

    $user = $stmt->fetch();

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    */

    if (!$user || !password_verify($password, $user['password_hash'])) {
        http_response_code(401);

        echo json_encode([
            'success' => false,
            'message' => 'Invalid credentials.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Account status
    |--------------------------------------------------------------------------
    */

    if ($user['status'] !== 'active') {
        http_response_code(403);

        echo json_encode([
            'success' => false,
            'message' => 'This account is not active.'
        ]);

        exit;
    }

    /*
    |--------------------------------------------------------------------------
    | Prevent session fixation
    |--------------------------------------------------------------------------
    */

    session_regenerate_id(true);

    /*
    |--------------------------------------------------------------------------
    | Store authenticated user
    |--------------------------------------------------------------------------
    */

    $_SESSION['user_id'] = (int)$user['id'];
    $_SESSION['role']    = $user['role'];
    $_SESSION['handle']  = $user['handle'];

    /*
    |--------------------------------------------------------------------------
    | Response
    |--------------------------------------------------------------------------
    */

    echo json_encode([
        'success' => true,
        'message' => 'Login successful.',
        'user' => [
            'id'       => (int)$user['id'],
            'fullName' => $user['full_name'],
            'handle'   => $user['handle'],
            'email'    => $user['email'],
            'role'     => $user['role']
        ]
    ]);

} catch (PDOException $e) {

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Login failed.'
    ]);
}