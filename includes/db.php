<?php
/**
 * Database Connection
 * 
 * Provides PDO database connection using environment variables.
 */

require_once __DIR__ . '/../envloader.php';

/**
 * Get PDO database connection
 * 
 * @return PDO
 * @throws PDOException
 */
function getDbConnection(): PDO
{
    static $pdo = null;

    if ($pdo === null) {
        $host = env('DB_HOST', 'localhost');
        $dbname = env('DB_NAME', 'garden_tour');
        $user = env('DB_USER', 'root');
        $pass = env('DB_PASS', env('DB_PASSWORD', ''));

        $dsn = "mysql:host=$host;dbname=$dbname;charset=utf8mb4";

        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];

        $pdo = new PDO($dsn, $user, $pass, $options);
    }

    return $pdo;
}

/**
 * Execute a query and return all results
 */
function dbQuery(string $sql, array $params = []): array
{
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Execute a query and return single row
 */
function dbQueryOne(string $sql, array $params = []): ?array
{
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute($params);
    $result = $stmt->fetch();
    return $result ?: null;
}

/**
 * Execute an insert/update/delete and return affected rows
 */
function dbExecute(string $sql, array $params = []): int
{
    $stmt = getDbConnection()->prepare($sql);
    $stmt->execute($params);
    return $stmt->rowCount();
}

/**
 * Get last inserted ID
 */
function dbLastInsertId(): string
{
    return getDbConnection()->lastInsertId();
}

/**
 * Generate a secure random token
 */
function generateToken(int $length = 64): string
{
    return bin2hex(random_bytes($length / 2));
}
