<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

// Shared session setup: protect the session cookie from scripts and cross-site requests.
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

// Escape untrusted text before placing it in HTML.
function e(string|int|null $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Redirect after a successful action, then stop the current page.
function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}

// Keep one unpredictable token per session to verify form submissions.
function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

// Add the session token to a POST form.
function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

// Reject form submissions that do not contain this session's token.
function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(400);
        exit('This request could not be verified. Please go back and try again.');
    }
}

// Save a one-time notice to display after redirecting.
function flash(string $message, string $type = 'success'): void
{
    $_SESSION['flash'] = ['message' => $message, 'type' => $type];
}

// Print and then clear the one-time notice.
function render_flash(): void
{
    if (empty($_SESSION['flash'])) {
        return;
    }

    $notice = $_SESSION['flash'];
    unset($_SESSION['flash']);
    $type = in_array($notice['type'], ['success', 'error', 'warning'], true)
        ? $notice['type']
        : 'success';
    echo '<p class="alert alert-' . e($type) . '" role="status">' .
        e($notice['message']) . '</p>';
}

// Allow student pages only when a student is signed in.
function require_student(): array
{
    if (($_SESSION['role'] ?? '') !== 'student' || empty($_SESSION['user_id'])) {
        flash('Please log in with your student account.', 'error');
        redirect('login.php');
    }

    return [
        'id' => (int) $_SESSION['user_id'],
        'name' => (string) $_SESSION['name'],
    ];
}

// Allow staff pages only when an admin is signed in.
function require_admin(): array
{
    if (($_SESSION['role'] ?? '') !== 'admin' || empty($_SESSION['user_id'])) {
        flash('Please log in with your admin account.', 'error');
        redirect('admin-login.php');
    }

    return [
        'id' => (int) $_SESSION['user_id'],
        'name' => (string) $_SESSION['name'],
    ];
}

// Render the shared document head, photo backgrounds, and role-appropriate header.
function page_start(string $title, string $bodyClass = '', string $page = ''): void
{
    $bodyAttributes = $page === 'dashboard' ? ' data-page="dashboard"' : '';
    $bodyAttributes .= $bodyClass !== '' ? ' class="' . e($bodyClass) . '"' : '';
    ?>
<!DOCTYPE html>
<html lang="en">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?> | PHINMA UPang Library</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="assets/css/alerts.css">
  </head>
  <body<?= $bodyAttributes ?>>
    <div class="library-slideshow" aria-hidden="true">
      <img src="assets/images/library-entry.jpg" alt="">
      <img src="assets/images/library-books.jpg" alt="">
      <img src="assets/images/library-background.jpg" alt="">
    </div>
    <div class="campus-background" aria-hidden="true">
      <img src="assets/images/phinma-upang-campus.png" alt="">
    </div>
    <div class="topbar">School Library System &middot; University of Pangasinan, Urdaneta City</div>
    <header class="site-header">
      <div class="wrap nav">
        <a class="brand" href="index.html">
          <span>
            <strong>PHINMA</strong> University of Pangasinan
            <small>School Library System</small>
          </span>
        </a>
        <?php if ($page === '' && $bodyClass === 'auth-page' && $title === 'Log in'): ?>
        <nav>
          <a href="index.html">Home</a>
          <a class="btn btn-yellow" href="register.php">Register</a>
        </nav>
        <?php elseif ($page === '' && $bodyClass === 'auth-page' && $title === 'Register'): ?>
        <nav>
          <a href="index.html">Home</a>
          <a class="btn btn-yellow" href="login.php">Log in</a>
        </nav>
        <?php elseif ($page === '' && $bodyClass === 'auth-page' && $title === 'Admin log in'): ?>
        <nav>
          <a href="index.html">Home</a>
          <a href="login.php">Student log in</a>
        </nav>
        <?php elseif ($page === ''): ?>
        <nav>
          <a href="index.html">Home</a>
          <a href="login.php">Student log in</a>
          <a class="btn btn-yellow" href="register.php">Register</a>
        </nav>
        <?php endif; ?>
      </div>
    </header>
<?php
}

function page_end(): void
{
    ?>
    <footer class="site-footer">
      <div class="wrap">
        <p>
          <strong>PHINMA University of Pangasinan Library</strong>
          &middot; Open Monday to Saturday, 7:30 AM &ndash; 6:00 PM
        </p>
        <p>&copy; 2026 School Library System.</p>
      </div>
    </footer>
  </body>
</html>
<?php
}

// Render the student navigation and mark the current page as active.
function student_sidebar(string $active): void
{
    $links = [
        'dashboard' => ['dashboard.php#profile', 'Profile'],
        'card' => ['library-card.php', 'Library card'],
        'visits' => ['library-visits.php', 'Check in / out &amp; visits'],
        'books' => ['borrowed-books.php', 'Borrowed books'],
    ];
    ?>
      <aside class="dashboard-sidebar" aria-label="Dashboard navigation">
        <p class="sidebar-heading">Student dashboard</p>
        <nav class="sidebar-nav">
          <?php foreach ($links as $key => [$href, $label]): ?>
          <a href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= $label ?></a>
          <?php endforeach; ?>
          <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button class="btn logout-button" type="submit">Log out</button>
          </form>
        </nav>
      </aside>
<?php
}

// Render the admin navigation and mark the current page as active.
function admin_sidebar(string $active): void
{
    $links = [
        'requests' => ['admin-requests.php', 'Book requests'],
        'records' => ['admin-records.php', 'Student records'],
        'inventory' => ['admin-inventory.php', 'Book Records'],
        'returns' => ['admin-dashboard.php', 'Book returns'],
        'accounts' => ['admin-accounts.php', 'Librarian accounts'],
    ];
    ?>
      <aside class="dashboard-sidebar" aria-label="Admin dashboard navigation">
        <p class="sidebar-heading">Admin portal</p>
        <nav class="sidebar-nav">
          <?php foreach ($links as $key => [$href, $label]): ?>
          <a href="<?= e($href) ?>"<?= $key === $active ? ' aria-current="page"' : '' ?>><?= $label ?></a>
          <?php endforeach; ?>
          <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button class="btn logout-button" type="submit">Log out</button>
          </form>
        </nav>
      </aside>
<?php
}

// Log technical details privately while returning a safe message to the visitor.
function database_error(PDOException $exception): string
{
    error_log($exception->getMessage());
    return 'The request could not be completed because of a database error. Please try again.';
}

// Read a single text field from a POST request and normalize surrounding spaces.
function post_value(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}
