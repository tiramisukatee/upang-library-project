<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
$student = require_student();
$error = '';

// Load the current profile before rendering the editable form.
try {
    $statement = db()->prepare('SELECT * FROM students WHERE id = :id');
    $statement->execute(['id' => $student['id']]);
    $profile = $statement->fetch();
    if (!$profile) {
        http_response_code(404);
        exit('Student account was not found.');
    }
} catch (PDOException $exception) {
    $error = database_error($exception);
    $profile = [
        'full_name' => $student['name'],
        'course' => '',
        'email' => '',
        'contact_number' => '',
        'address' => '',
        'profile_picture' => null,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate profile fields and the optional image before updating the database.
    verify_csrf();
    $name = post_value('full_name');
    $course = post_value('course');
    $email = post_value('email');
    $phone = post_value('contact_number');
    $address = post_value('address');
    $picture = $profile['profile_picture'];

    // Preserve submitted values if validation fails and the form is redisplayed.
    $profile['full_name'] = $name;
    $profile['course'] = $course;
    $profile['email'] = $email;
    $profile['contact_number'] = $phone;
    $profile['address'] = $address;

    if (
        $name === '' || strlen($name) > 100 || $course === '' || strlen($course) > 60 ||
        !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 120 ||
        !preg_match('/^9[0-9]{9}$/', $phone) || $address === '' || strlen($address) > 250
    ) {
        $error = 'Please check your profile details and enter a valid email and mobile number.';
    } elseif (isset($_FILES['profile_picture'])) {
        $upload = $_FILES['profile_picture'];
        if (!is_array($upload)) {
            $error = 'Choose a valid profile picture.';
        } else {
            $mimeTypes = [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ];
            if (($upload['error'] ?? null) === UPLOAD_ERR_NO_FILE) {
                // A profile update does not require a new picture.
            } elseif (
                !isset($upload['error'], $upload['tmp_name'], $upload['size']) ||
                !is_int($upload['error']) || !is_string($upload['tmp_name']) ||
                !is_int($upload['size']) || $upload['error'] !== UPLOAD_ERR_OK ||
                $upload['size'] > 2 * 1024 * 1024 || !is_uploaded_file($upload['tmp_name'])
            ) {
                $error = 'Choose a valid image no larger than 2 MB.';
            } else {
                // Trust the detected MIME type, not the user-supplied filename or extension.
                $mime = (new finfo(FILEINFO_MIME_TYPE))->file($upload['tmp_name']);
                if (!isset($mimeTypes[$mime])) {
                    $error = 'Profile pictures must be JPG, PNG, or WebP images.';
                } else {
                    $uploadDirectory = __DIR__ . '/uploads/profile';
                    if (
                        !is_dir($uploadDirectory) &&
                        !mkdir($uploadDirectory, 0755, true) &&
                        !is_dir($uploadDirectory)
                    ) {
                        $error = 'The profile picture could not be saved. Please try again.';
                    } else {
                        $filename = bin2hex(random_bytes(16)) . '.' . $mimeTypes[$mime];
                        $uploadedPath = 'uploads/profile/' . $filename;
                        if (!move_uploaded_file($upload['tmp_name'], __DIR__ . '/' . $uploadedPath)) {
                            $error = 'The profile picture could not be saved. Please try again.';
                        } else {
                            $picture = $uploadedPath;
                        }
                    }
                }
            }
        }
    }

    if ($error === '') {
        try {
            // Save profile data only after every field and uploaded image passes validation.
            $statement = db()->prepare(
                'UPDATE students
                 SET full_name = :full_name, course = :course, email = :email,
                     contact_number = :contact_number, address = :address,
                     profile_picture = :profile_picture
                 WHERE id = :id'
            );
            $statement->execute([
                'full_name' => $name,
                'course' => $course,
                'email' => $email,
                'contact_number' => $phone,
                'address' => $address,
                'profile_picture' => $picture,
                'id' => $student['id'],
            ]);
            $_SESSION['name'] = $name;
            flash('Your profile was updated.');
            redirect('dashboard.php');
        } catch (PDOException $exception) {
            $error = database_error($exception);
            if ((string) $exception->getCode() === '23000') {
                $error = 'That email address is already used by another student.';
            }
        }
    }
}

// Tell the shared dashboard layout which page and sidebar item to display.
$title = 'Student dashboard';
$staff = false;
$active = 'dashboard';
require __DIR__ . '/includes/dashboard_start.php';
?>
        <h1 class="page-title">Welcome, <?= e($profile['full_name']) ?>.</h1>
        <p class="muted"><?= e($profile['student_no']) ?> &middot; <?= e($profile['course']) ?></p>
        <section class="panel dashboard-panel profile-edit-panel" id="profile" aria-labelledby="profile-edit-heading">
          <div class="profile-edit-heading">
            <h2 id="profile-edit-heading">Edit profile</h2>
            <p class="muted">Update your contact details and profile picture.</p>
          </div>
          <?php render_flash(); ?>
          <?php if ($error !== ''): ?>
          <p class="alert alert-error" role="alert"><?= e($error) ?></p>
          <?php endif; ?>
          <!-- Send profile fields and the optional image together as a multipart form. -->
          <form class="profile-edit-form" method="post" action="dashboard.php" enctype="multipart/form-data">
            <?= csrf_field() ?>
            <div class="profile-photo-control">
              <div class="profile-photo-placeholder" aria-hidden="true">
                <?php if (!empty($profile['profile_picture'])): ?>
                <img
                  src="<?= e($profile['profile_picture']) ?>"
                  alt=""
                  width="96"
                  height="96"
                  style="width: 96px; height: 96px; object-fit: cover;"
                >
                <?php else: ?>
                1×1
                <?php endif; ?>
              </div>
              <label>
                1×1 profile picture
                <input type="file" name="profile_picture" accept="image/jpeg,image/png,image/webp">
                <small>Choose a square JPG, PNG, or WebP image (2 MB maximum).</small>
              </label>
            </div>
            <label>
              Full name
              <input
                type="text"
                name="full_name"
                autocomplete="name"
                maxlength="100"
                required
                value="<?= e($profile['full_name']) ?>"
              >
            </label>
            <label>
              Course / Program & Year level 
              <input
                type="text"
                name="course"
                autocomplete="organization-title"
                maxlength="60"
                required
                value="<?= e($profile['course']) ?>"
              >
            </label>
            <label>
              School email
              <input
                type="email"
                name="email"
                autocomplete="email"
                maxlength="120"
                required
                value="<?= e($profile['email']) ?>"
              >
            </label>
            <label>
              Contact number
              <span class="phone-input">
                <span class="phone-prefix" aria-hidden="true">+63</span>
                <input
                  type="tel"
                  name="contact_number"
                  maxlength="10"
                  pattern="9[0-9]{9}"
                  placeholder="9XXXXXXXXX"
                  autocomplete="tel-national"
                  aria-label="Philippine mobile number after +63"
                  required
                  value="<?= e($profile['contact_number']) ?>"
                >
              </span>
            </label>
            <label class="profile-address">
              Address
              <textarea
                name="address"
                maxlength="250"
                rows="3"
                autocomplete="street-address"
                required
              ><?= e($profile['address']) ?></textarea>
            </label>
            <div class="profile-edit-actions">
              <button class="btn btn-green" type="submit">Save changes</button>
            </div>
          </form>
        </section>
      </main>
    </div>
<?php page_end(); ?>
