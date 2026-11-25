<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
$admin = require_admin();
$error = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $username = post_value('username');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmation = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';

    if ($username === '' || strlen($username) > 60) {
        $error = 'Enter a username of up to 60 characters.';
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*[0-9]).{8,}$/', $password)) {
        $error = 'Password must be at least 8 characters and contain a letter and a number.';
    } elseif (!hash_equals($password, $confirmation)) {
        $error = 'The passwords do not match.';
    } else {
        try {
            $statement = db()->prepare(
                'INSERT INTO admins (username, password_hash)
                 VALUES (:username, :password_hash)'
            );
            $statement->execute([
                'username' => $username,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            flash('Librarian account created.');
            redirect('admin-accounts.php');
        } catch (PDOException $exception) {
            if ((string) $exception->getCode() === '23000') {
                $error = 'That admin username is already in use.';
            } else {
                $error = database_error($exception);
            }
        }
    }
}

$title = 'Librarian accounts';
$staff = true;
$active = 'accounts';
require __DIR__ . '/includes/dashboard_start.php';
?>
        <h1 class="page-title">Librarian accounts</h1>
        <p class="muted">Create an account for a librarian who needs access to the admin portal.</p>
        <?php render_flash(); ?>
        <?php if ($error !== ''): ?>
        <p class="alert alert-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <section class="panel dashboard-panel" aria-labelledby="create-account-heading">
          <h2 id="create-account-heading">Create librarian account</h2>
          <form method="post" action="admin-accounts.php">
            <?= csrf_field() ?>
            <label>
              Admin username
              <input name="username" required maxlength="60" autocomplete="username" value="<?= e($username) ?>">
            </label>
            <label>
              Password
              <input
                type="password"
                name="password"
                required
                minlength="8"
                pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,}"
                autocomplete="new-password"
              >
              <small>At least 8 characters, with a letter and a number.</small>
            </label>
            <label>
              Confirm password
              <input type="password" name="confirm" required minlength="8" autocomplete="new-password">
            </label>
            <button class="btn btn-green" type="submit">Create librarian account</button>
          </form>
        </section>
      </main>
    </div>
<?php page_end(); ?>
