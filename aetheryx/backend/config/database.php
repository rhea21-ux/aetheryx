<?php

declare(strict_types=1);

/*
|--------------------------------------------------------------------------
| ÆTHERYX Database Connection
|--------------------------------------------------------------------------
*/

$host = getenv('DB_HOST') ?: '127.0.0.1';
$port = getenv('DB_PORT') ?: '3306';
$dbname = getenv('DB_NAME') ?: 'aetheryx_db';
$username = getenv('DB_USER') ?: 'root';
$password = getenv('DB_PASSWORD') ?: '';
$sslCa = getenv('DB_SSL_CA') ?: '';

$charset = 'utf8mb4';

$dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset={$charset}";

// Aiven MySQL requires TLS. Verify the certificate when a CA file is mounted;
// otherwise still require encryption so deployments without a mounted CA work.
if ($sslCa !== '') {
    $dsn .= ";sslmode=verify-ca;sslrootcert='{$sslCa}'";
} elseif (!in_array(strtolower($host), ['127.0.0.1', 'localhost', '::1'], true)) {
    $dsn .= ';sslmode=require';
}

$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        $options
    );
} catch (PDOException $e) {
    error_log('ÆTHERYX database connection error: ' . $e->getMessage());

    http_response_code(500);

    header('Content-Type: application/json; charset=utf-8');

    echo json_encode([
        'success' => false,
        'message' => 'Database connection failed.',
        'code' => 'DATABASE_UNAVAILABLE'
    ]);

    exit;
}
