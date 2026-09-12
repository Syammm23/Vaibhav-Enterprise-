<?php
/** Single shared PDO handle. */

require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME);

    try {
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        http_response_code(500);
        if (DEBUG) {
            exit('<h1>Database connection failed</h1><pre>' . htmlspecialchars($e->getMessage()) . '</pre>');
        }
        exit('<h1>Market is temporarily unavailable</h1><p>The store could not reach its database. '
           . 'Check <code>config/config.php</code> and make sure MySQL is running.</p>');
    }

    return $pdo;
}

/** Run a prepared statement and hand back the statement. */
function q(string $sql, array $params = []): PDOStatement
{
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

/** First row, or null. */
function q1(string $sql, array $params = []): ?array
{
    $row = q($sql, $params)->fetch();
    return $row === false ? null : $row;
}

/** All rows. */
function qa(string $sql, array $params = []): array
{
    return q($sql, $params)->fetchAll();
}

/** First column of the first row. */
function qv(string $sql, array $params = [])
{
    $val = q($sql, $params)->fetchColumn();
    return $val === false ? null : $val;
}
