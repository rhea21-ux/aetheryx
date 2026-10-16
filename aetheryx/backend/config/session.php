<?php

declare(strict_types=1);

/**
 * Start the session with safe cookie settings.
 * Safe to call more than once.
 */
function startSecureSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    // The connection must remain available to the caller after this function
    // returns. Declaring it global also lets the include below populate the
    // shared PDO handle when this is the first place database.php is loaded.
    global $pdo;

    // Persist sessions in MySQL so authentication survives container restarts
    // and requests routed to different application instances.
    require_once __DIR__ . '/database.php';

    // A missing session table otherwise makes session_start() fail before an
    // API endpoint can return its normal JSON response. Check first so the
    // application DB user only needs CREATE permission when the table is absent.
    $sessionTableCheck = $pdo->query(
        "SELECT 1 FROM information_schema.tables
         WHERE table_schema = DATABASE()
           AND table_name = 'aetheryx_sessions'
         LIMIT 1"
    );

    if (!$sessionTableCheck->fetchColumn()) {
        $pdo->exec(
            "CREATE TABLE `aetheryx_sessions` (
                `session_id` VARCHAR(128) COLLATE ascii_bin NOT NULL,
                `session_data` MEDIUMBLOB NOT NULL,
                `last_activity` INT UNSIGNED NOT NULL,
                PRIMARY KEY (`session_id`),
                KEY `idx_aetheryx_sessions_activity` (`last_activity`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
    }

    $handler = new AetheryxPdoSessionHandler($pdo);
    session_set_save_handler($handler, true);

    // Authentication is carried in a per-tab bearer header, never in a shared cookie.
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_cookies', '0');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');

    session_start();
}

final class AetheryxPdoSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $stmt = $this->pdo->prepare(
            'SELECT session_data, last_activity FROM aetheryx_sessions WHERE session_id = :id LIMIT 1'
        );
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return '';
        }

        if ((int) $row['last_activity'] < time() - $this->maxLifetime()) {
            $this->destroy($id);
            return '';
        }

        return (string) $row['session_data'];
    }

    public function write(string $id, string $data): bool
    {
        $stmt = $this->pdo->prepare(
            'INSERT INTO aetheryx_sessions (session_id, session_data, last_activity)
             VALUES (:id, :data, :activity)
             ON DUPLICATE KEY UPDATE session_data = VALUES(session_data), last_activity = VALUES(last_activity)'
        );

        return $stmt->execute([
            ':id' => $id,
            ':data' => $data,
            ':activity' => time(),
        ]);
    }

    public function destroy(string $id): bool
    {
        $stmt = $this->pdo->prepare('DELETE FROM aetheryx_sessions WHERE session_id = :id');
        return $stmt->execute([':id' => $id]);
    }

    public function gc(int $max_lifetime): int|false
    {
        $stmt = $this->pdo->prepare('DELETE FROM aetheryx_sessions WHERE last_activity < :expiry');
        $stmt->execute([':expiry' => time() - $max_lifetime]);
        return $stmt->rowCount();
    }

    public function validateId(string $id): bool
    {
        $stmt = $this->pdo->prepare(
            'SELECT 1 FROM aetheryx_sessions WHERE session_id = :id AND last_activity >= :expiry LIMIT 1'
        );
        $stmt->execute([
            ':id' => $id,
            ':expiry' => time() - $this->maxLifetime(),
        ]);

        return (bool) $stmt->fetchColumn();
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        // Refresh both payload and timestamp to cover handlers without a row.
        return $this->write($id, $data);
    }

    private function maxLifetime(): int
    {
        return (int) (ini_get('session.gc_maxlifetime') ?: 1440);
    }
}
