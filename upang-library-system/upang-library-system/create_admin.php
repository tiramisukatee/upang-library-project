<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

// Provision staff accounts from the local command line, never from a web form.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}

// Prompt for credentials and reject values outside the database limits.
$username = trim((string) readline('Admin username: '));
$password = (string) readline('Password (input is visible): ');

if ($username === '' || strlen($username) > 60 || !preg_match('/^(?=.*[A-Za-z])(?=.*[0-9]).{8,}$/', $password)) {
    fwrite(STDERR, "Use a username of up to 60 characters and a password of at least 8 characters containing a letter and a number.\n");
    exit(1);
}

// Hash the password before inserting the new staff account.
try {
    $statement = db()->prepare(
        'INSERT INTO admins (username, password_hash) VALUES (:username, :password_hash)'
    );
    $statement->execute([
        'username' => $username,
        'password_hash' => password_hash($password, PASSWORD_DEFAULT),
    ]);
    fwrite(STDOUT, "Admin account created.\n");
} catch (PDOException $exception) {
    fwrite(STDERR, database_error($exception) . "\n");
    exit(1);
}
