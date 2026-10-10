<?php

declare(strict_types=1);

// Keep PHP warnings and fatal bootstrap details out of the JSON response.
// They are written to the application log instead.
ini_set('display_errors', '0');
ini_set('log_errors', '1');
header('Content-Type: application/json; charset=utf-8');
ob_start();

$loginFailureSent = false;
$sendLoginFailure = static function () use (&$loginFailureSent): void {
    if ($loginFailureSent) {
        return;
    }
    $loginFailureSent = true;

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Login service encountered an internal error.',
        'code' => 'LOGIN_INTERNAL_ERROR'
    ]);
};

set_exception_handler(static function (Throwable $e) use ($sendLoginFailure): void {
    error_log('AETHERYX unhandled login exception: ' . get_class($e) . ': ' . $e->getMessage());
    $sendLoginFailure();
});

register_shutdown_function(static function () use ($sendLoginFailure): void {
    $error = error_get_last();
    $fatalTypes = [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR];

    if ($error !== null && in_array($error['type'], $fatalTypes, true)) {
        error_log(sprintf(
            'AETHERYX fatal login error: %s in %s:%d',
            $error['message'],
            $error['file'],
            $error['line']
        ));
        $sendLoginFailure();
    }
});

try {
    require_once __DIR__ . '/../../config/session.php';
    startSecureSession();
    if (session_status() !== PHP_SESSION_ACTIVE) {
        throw new RuntimeException('PHP session did not become active.');
    }

    require_once __DIR__ . '/../../config/database.php';
} catch (Throwable $e) {
    error_log('AETHERYX login bootstrap error: ' . get_class($e) . ': ' . $e->getMessage());
    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Authentication service could not start.',
        'code' => 'AUTH_BOOTSTRAP_FAILED'
    ]);

    exit;
}

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

    // Persist the authenticated session before returning success so any
    // session-table write failure is caught as an API error, not appended as
    // an HTML warning after the JSON response.
    if (!session_write_close()) {
        throw new RuntimeException('Unable to persist the authenticated session.');
    }

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

} catch (Throwable $e) {
    error_log('AETHERYX login processing error: ' . get_class($e) . ': ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'success' => false,
        'message' => 'Login service could not complete the request.',
        'code' => 'LOGIN_PROCESSING_FAILED'
    ]);
}
