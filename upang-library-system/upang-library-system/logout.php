<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

// Only accept logout through the protected form, not a clickable GET URL.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Use the log out button to end your session.');
}

// Clear the session and send the user to the matching login page.
verify_csrf();
$role = $_SESSION['role'] ?? '';
$_SESSION = [];
session_regenerate_id(true);
session_destroy();

if ($role === 'admin') {
    redirect('admin-login.php');
}
redirect('login.php');
