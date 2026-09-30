<?php
/**
 * Database connection (PDO).
 * Reads credentials from environment variables when set, otherwise
 * falls back to local XAMPP defaults. See README for setup instructions.
 * Never hard-code real production credentials here — this is local-dev only.
 */

$DB_HOST = getenv('DB_HOST') ?: '127.0.0.1';
$DB_NAME = getenv('DB_NAME') ?: 'nestdecor';
$DB_USER = getenv('DB_USER') ?: 'root';
$DB_PASS = getenv('DB_PASS') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4",
        $DB_USER,
        $DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false, // real prepared statements, prevents SQL injection
        ]
    );
} catch (PDOException $e) {
    // In production, log this instead of exposing it. For a university
    // project, showing a clear message is acceptable during development.
    http_response_code(500);
    die('Database connection failed: ' . $e->getMessage());
}
