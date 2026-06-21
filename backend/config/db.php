<?php
/**
 * Database connection (PDO).
 *
 * Returns a single configured PDO instance. We use exception error mode
 * so any SQL problem throws (and is caught by our route handlers) instead
 * of failing silently. Prepared statements everywhere protect against
 * SQL injection.
 *
 * XAMPP default MySQL port is 3306, user "root", empty password.
 * If your MySQL runs on a different port (e.g. MAMP uses 3306 or 8889),
 * change DB_PORT below.
 */

function getDBConnection(): PDO
{
    $host = '127.0.0.1';
    $port = '3307';          // XAMPP default. MAMP often uses 8889.
    $db   = 'campus_ems';
    $user = 'root';
    $pass = '';              // XAMPP default root password is empty.

    $dsn = "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4";

    $pdo = new PDO($dsn, $user, $pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);

    return $pdo;
}
