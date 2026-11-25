<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

// Authenticate admin accounts separately from student accounts.
$error = '';
$username = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = post_value('username');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    try {
        $statement = db()->prepare(
            'SELECT id, username, password_hash FROM admins WHERE username = :username'
        );
        $statement->execute(['username' => $username]);
        $admin = $statement->fetch();
        if ($admin && password_verify($password, $admin['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['role'] = 'admin';
            $_SESSION['user_id'] = (int) $admin['id'];
            $_SESSION['name'] = $admin['username'];
            redirect('admin-dashboard.php');
        }
        $error = 'The username or password is incorrect.';
    } catch (PDOException $exception) {
        $error = database_error($exception);
    }
}

// Keep staff sign-in on the same visual form style as student sign-in.
page_start('Admin log in', 'auth-page');
?>
    <main class="wrap">
      <section class="form-card">
        <h2>Admin log in</h2>
        <p class="muted">Enter your admin credentials.</p>
        <?php render_flash(); ?>
        <?php if ($error !== ''): ?>
        <p class="alert alert-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <form method="post" action="admin-login.php">
          <?= csrf_field() ?>
          <label>
            Admin username
            <input name="username" required maxlength="60" autocomplete="username" value="<?= e($username) ?>">
          </label>
          <label>
            Password
            <input type="password" name="password" required autocomplete="current-password">
          </label>
          <button class="btn btn-green" type="submit">Log in</button>
        </form>
      </section>
    </main>
<?php page_end(); ?>
