<?php
declare(strict_types=1);

// Database settings: XAMPP defaults work locally; environment variables can
// supply separate credentials when the application is deployed.
const DB_NAME = 'school_library_system';

date_default_timezone_set('Asia/Manila');

function db(): PDO
{
    static $connection = null;

    if ($connection === null) {
        // Create the connection only when a page first needs to query MySQL.
        $host = getenv('LIBRARY_DB_HOST') ?: '127.0.0.1';
        $name = getenv('LIBRARY_DB_NAME') ?: DB_NAME;
        $user = getenv('LIBRARY_DB_USER') ?: 'root';
        $password = getenv('LIBRARY_DB_PASSWORD') ?: '';
        $dsn = "mysql:host={$host};dbname={$name};charset=utf8mb4";

        $connection = new PDO($dsn, $user, $password, [
            // Throw database errors so each page can show a useful message.
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            // Return rows by column name and use native prepared statements.
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }

    return $connection;
}
