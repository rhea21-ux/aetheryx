<?php

declare(strict_types=1);

require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json; charset=utf-8');

/*
|--------------------------------------------------------------------------
| ONLY POST IS ALLOWED
|--------------------------------------------------------------------------
*/

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {

    http_response_code(405);

    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| READ JSON
|--------------------------------------------------------------------------
*/

$rawInput = file_get_contents('php://input');

$data = json_decode($rawInput, true);

if (!is_array($data)) {

    http_response_code(400);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid JSON request.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| GET VALUES
|--------------------------------------------------------------------------
*/

$fullName = trim(
    (string)($data['fullName'] ?? '')
);

$handle = trim(
    (string)($data['handle'] ?? '')
);

$email = strtolower(
    trim((string)($data['email'] ?? ''))
);

$password = (string)(
    $data['password'] ?? ''
);

$role = strtolower(
    trim((string)($data['role'] ?? ''))
);


/*
|--------------------------------------------------------------------------
| NORMALIZE ROLE
|--------------------------------------------------------------------------
|
| User-facing:
| VILLAIN
|
| Database:
| enemy
|
| We also accept old spellings so old frontend
| code cannot accidentally break registration.
|
*/

if (
    $role === 'villain' ||
    $role === 'villian' ||
    $role === 'enemy'
) {

    $role = 'enemy';

} elseif ($role === 'hero') {

    $role = 'hero';

} else {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Invalid role. Choose HERO or VILLAIN.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| ADMIN CANNOT REGISTER PUBLICLY
|--------------------------------------------------------------------------
*/

if ($role === 'admin') {

    http_response_code(403);

    echo json_encode([
        'success' => false,
        'message' => 'Administrator registration is not allowed.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| REQUIRED FIELDS
|--------------------------------------------------------------------------
*/

if (
    $fullName === '' ||
    $handle === '' ||
    $email === '' ||
    $password === ''
) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'All required fields must be completed.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| FULL NAME
|--------------------------------------------------------------------------
*/

if (mb_strlen($fullName) < 2) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Full name must contain at least 2 characters.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| HANDLE
|--------------------------------------------------------------------------
*/

if (!preg_match('/^[A-Za-z0-9_-]{3,30}$/', $handle)) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' =>
            'Handle must be 3–30 characters and use only letters, numbers, underscores, or hyphens.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| EMAIL
|--------------------------------------------------------------------------
*/

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' => 'Please provide a valid email address.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| PASSWORD
|--------------------------------------------------------------------------
*/

if (strlen($password) < 8) {

    http_response_code(422);

    echo json_encode([
        'success' => false,
        'message' =>
            'Password must contain at least 8 characters.'
    ]);

    exit;
}


/*
|--------------------------------------------------------------------------
| DATABASE
|--------------------------------------------------------------------------
*/

try {

    /*
    |--------------------------------------------------------------------------
    | CHECK DUPLICATE EMAIL OR HANDLE
    |--------------------------------------------------------------------------
    */

    $check = $pdo->prepare(
        'SELECT id
         FROM users
         WHERE email = :email
            OR handle = :handle
         LIMIT 1'
    );

    $check->execute([
        ':email' => $email,
        ':handle' => $handle
    ]);

    if ($check->fetch()) {

        http_response_code(409);

        echo json_encode([
            'success' => false,
            'message' =>
                'That email or handle is already registered.'
        ]);

        exit;
    }


    /*
    |--------------------------------------------------------------------------
    | HASH PASSWORD
    |--------------------------------------------------------------------------
    */

    $passwordHash = password_hash(
        $password,
        PASSWORD_DEFAULT
    );

    if ($passwordHash === false) {

        throw new RuntimeException(
            'Password hashing failed.'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | INSERT USER
    |--------------------------------------------------------------------------
    */

    $stmt = $pdo->prepare(
        'INSERT INTO users
        (
            full_name,
            handle,
            email,
            password_hash,
            role,
            status
        )
        VALUES
        (
            :full_name,
            :handle,
            :email,
            :password_hash,
            :role,
            :status
        )'
    );


    $stmt->execute([

        ':full_name' =>
            $fullName,

        ':handle' =>
            $handle,

        ':email' =>
            $email,

        ':password_hash' =>
            $passwordHash,

        ':role' =>
            $role,

        ':status' =>
            'active'

    ]);


    /*
    |--------------------------------------------------------------------------
    | SUCCESS
    |--------------------------------------------------------------------------
    */

    $userId = (int)$pdo->lastInsertId();

    $displayRole =
        $role === 'enemy'
            ? 'VILLAIN'
            : 'HERO';


    http_response_code(201);

    echo json_encode([

        'success' => true,

        'message' =>
            'Registration successful.',

        'user' => [

            'id' =>
                $userId,

            'fullName' =>
                $fullName,

            'handle' =>
                $handle,

            'email' =>
                $email,

            'role' =>
                $role,

            'displayRole' =>
                $displayRole,

            'status' =>
                'active'

        ]

    ]);

    exit;


} catch (PDOException $e) {

    error_log(
        'ÆTHERYX registration PDO error: ' .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([

        'success' => false,

        'message' =>
            'Registration failed. Please try again.'

    ]);

    exit;


} catch (Throwable $e) {

    error_log(
        'ÆTHERYX registration error: ' .
        $e->getMessage()
    );

    http_response_code(500);

    echo json_encode([

        'success' => false,

        'message' =>
            'Registration failed. Please try again.'

    ]);

    exit;
}