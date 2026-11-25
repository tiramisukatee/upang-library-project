<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

// Authenticate student credentials and create a fresh session on success.
$error = '';
$studentNumber = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $studentNumber = post_value('student_no');
    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';

    try {
        // Prepared statements keep the student number as data, not SQL syntax.
        $statement = db()->prepare(
            'SELECT id, full_name, password_hash FROM students WHERE student_no = :student_no'
        );
        $statement->execute(['student_no' => $studentNumber]);
        $student = $statement->fetch();
        if ($student && password_verify($password, $student['password_hash'])) {
            session_regenerate_id(true);
            $_SESSION['role'] = 'student';
            $_SESSION['user_id'] = (int) $student['id'];
            $_SESSION['name'] = $student['full_name'];
            redirect('dashboard.php');
        }
        $error = 'The student number or password is incorrect.';
    } catch (PDOException $exception) {
        $error = database_error($exception);
    }
}

// Display the existing student login design with a server-rendered error if needed.
page_start('Log in', 'auth-page');
?>
    <main class="wrap">
      <section class="form-card">
        <h2>Student log in</h2>
        <?php render_flash(); ?>
        <?php if ($error !== ''): ?>
        <p class="alert alert-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <form method="post" action="login.php">
          <?= csrf_field() ?>
          <label>
            Student number
            <input name="student_no" required autocomplete="username" value="<?= e($studentNumber) ?>">
          </label>
          <label>
            Password
            <input type="password" name="password" required autocomplete="current-password">
          </label>
          <button class="btn btn-green" type="submit">Log in</button>
          <p class="alt">New student? <a href="register.php">Register here</a></p>
        </form>
      </section>
    </main>
<?php page_end(); ?>
