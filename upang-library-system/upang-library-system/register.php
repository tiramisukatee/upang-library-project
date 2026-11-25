<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';

// Keep submitted profile fields so the student can correct validation errors.
$error = '';
$fields = [
    'student_no' => '',
    'full_name' => '',
    'course' => '',
    'email' => '',
    'contact_number' => '',
    'address' => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate the request and all fields again on the server before saving.
    verify_csrf();
    foreach ($fields as $key => $_) {
        $fields[$key] = post_value($key);
    }

    $password = is_string($_POST['password'] ?? null) ? $_POST['password'] : '';
    $confirmation = is_string($_POST['confirm'] ?? null) ? $_POST['confirm'] : '';
    if (
        $fields['student_no'] === '' || strlen($fields['student_no']) > 20 ||
        $fields['full_name'] === '' || strlen($fields['full_name']) > 100 ||
        $fields['course'] === '' || strlen($fields['course']) > 60 ||
        !filter_var($fields['email'], FILTER_VALIDATE_EMAIL) || strlen($fields['email']) > 120 ||
        !preg_match('/^9[0-9]{9}$/', $fields['contact_number']) ||
        $fields['address'] === '' || strlen($fields['address']) > 250
    ) {
        $error = 'Please check the required fields and enter a valid school email and 10-digit mobile number.';
    } elseif (!preg_match('/^(?=.*[A-Za-z])(?=.*[0-9]).{8,}$/', $password)) {
        $error = 'Password must be at least 8 characters and contain a letter and a number.';
    } elseif (!hash_equals($password, $confirmation)) {
        $error = 'The passwords do not match.';
    } else {
        try {
            // Store a password hash, never the original password.
            $statement = db()->prepare(
                'INSERT INTO students
                    (student_no, full_name, course, email, contact_number, address, password_hash)
                 VALUES
                    (:student_no, :full_name, :course, :email, :contact_number, :address, :password_hash)'
            );
            $statement->execute([
                'student_no' => $fields['student_no'],
                'full_name' => $fields['full_name'],
                'course' => $fields['course'],
                'email' => $fields['email'],
                'contact_number' => $fields['contact_number'],
                'address' => $fields['address'],
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            flash('Your account was created. You can now log in.');
            redirect('login.php');
        } catch (PDOException $exception) {
            $error = database_error($exception);
            if ((string) $exception->getCode() === '23000') {
                $error = 'That student number or email address is already registered.';
            }
        }
    }
}

// Render the same registration card used by the original front end.
page_start('Register', 'auth-page');
?>
    <main class="wrap">
      <section class="form-card">
        <h2>Student registration</h2>
        <?php render_flash(); ?>
        <?php if ($error !== ''): ?>
        <p class="alert alert-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <form method="post" action="register.php">
          <?= csrf_field() ?>
          <label>
            Student number
            <input
              name="student_no"
              required
              maxlength="20"
              placeholder="e.g. 21-1234-567"
              autocomplete="username"
              value="<?= e($fields['student_no']) ?>"
            >
          </label>
          <label>
            Full name
            <input
              name="full_name"
              required
              maxlength="100"
              autocomplete="name"
              value="<?= e($fields['full_name']) ?>"
            >
          </label>
          <label>
            Course / Program
            <input
              name="course"
              required
              maxlength="60"
              placeholder="e.g. BS Information Technology"
              value="<?= e($fields['course']) ?>"
            >
          </label>
          <label>
            School email
            <input
              type="email"
              name="email"
              required
              maxlength="120"
              autocomplete="email"
              value="<?= e($fields['email']) ?>"
            >
          </label>
          <label>
            Contact number
            <span class="phone-input">
              <span class="phone-prefix" aria-hidden="true">+63</span>
              <input
                type="tel"
                name="contact_number"
                required
                maxlength="10"
                pattern="9[0-9]{9}"
                placeholder="9XXXXXXXXX"
                autocomplete="tel-national"
                aria-label="Philippine mobile number after +63"
                value="<?= e($fields['contact_number']) ?>"
              >
            </span>
            <small>Enter your 10-digit mobile number after +63.</small>
          </label>
          <label>
            Address
            <textarea
              name="address"
              required
              maxlength="250"
              rows="3"
              autocomplete="street-address"
            ><?= e($fields['address']) ?></textarea>
          </label>
          <label>
            Password
            <input type="password" name="password" required minlength="8" pattern="(?=.*[A-Za-z])(?=.*[0-9]).{8,}" autocomplete="new-password">
            <small>At least 8 characters, with a letter and a number.</small>
          </label>
          <label>
            Confirm password
            <input type="password" name="confirm" required minlength="8" autocomplete="new-password">
          </label>
          <button class="btn btn-green" type="submit">Create account</button>
          <p class="alt">Already registered? <a href="login.php">Log in</a></p>
        </form>
      </section>
    </main>
<?php page_end(); ?>
