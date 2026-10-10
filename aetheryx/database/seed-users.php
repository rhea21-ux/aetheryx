<?php
// Run once from the command line:  php database/seed-users.php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Run this script from the command line only.');
}

require __DIR__ . '/../backend/config/database.php';

$accounts = [
    ['ÆTHERYX Administrator', 'admin', 'admin@aetheryx.local', 'Admin@12345', 'admin'],
    ['Demo Hero',             'hero1', 'hero@aetheryx.local',  'Hero@12345',  'hero'],
    ['Demo Villain',          'villain1', 'villain@aetheryx.local', 'Villain@12345', 'enemy'],
];

$stmt = $pdo->prepare(
    'INSERT INTO users (full_name, handle, email, password_hash, role, status)
     VALUES (:n, :h, :e, :p, :r, "active")
     ON DUPLICATE KEY UPDATE full_name = VALUES(full_name)'
);

foreach ($accounts as [$name, $handle, $email, $password, $role]) {
    $stmt->execute([':n' => $name, ':h' => $handle, ':e' => $email, ':p' => password_hash($password, PASSWORD_DEFAULT), ':r' => $role]);
    echo "$role account ready: $email\n";
}
echo "Done. Existing account passwords were preserved. Change any setup password after first login.\n";
