<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
$student = require_student();

// Load the signed-in student's identity for the card.
try {
    $statement = db()->prepare(
        'SELECT student_no, full_name, course, profile_picture
         FROM students WHERE id = :id'
    );
    $statement->execute(['id' => $student['id']]);
    $profile = $statement->fetch();
    if (!$profile) {
        http_response_code(404);
        exit('Student account was not found.');
    }
} catch (PDOException $exception) {
    $error = database_error($exception);
    $profile = [
        'student_no' => '',
        'full_name' => $student['name'],
        'course' => '',
        'profile_picture' => null,
    ];
}

// Render the student's dashboard navigation and their digital ID card.
$title = 'Library card';
$staff = false;
$active = 'card';
$bodyClass = 'library-card-page';
require __DIR__ . '/includes/dashboard_start.php';
?>
        <?php if (isset($error)): ?>
        <p class="alert alert-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <section class="panel dashboard-panel profile-page-panel">
          <div class="library-card-instructions">
            <h2>Digital Library ID Card</h2>
            <p>
              Present this digital pass at the entrance terminal or show it to
              library staff when checking in, checking out, or validating your visits.
            </p>
          </div>
          <article class="digital-library-card" aria-label="Digital library card">
            <div class="library-card-heading">
              <img src="assets/images/upang-seal.png" alt="">
              <span>
                <strong>PHINMA UNIVERSITY OF PANGASINAN</strong>
                <small>Digital Library Card</small>
              </span>
            </div>
            <div class="library-card-identity">
              <?php if (!empty($profile['profile_picture'])): ?>
              <img
                class="library-card-photo"
                src="<?= e($profile['profile_picture']) ?>"
                alt="Student profile photo"
              >
              <?php else: ?>
              <div class="library-card-photo-placeholder" aria-label="No profile photo uploaded">
                Student photo
              </div>
              <?php endif; ?>
              <div class="library-card-details">
                <span class="library-card-label">STUDENT</span>
                <strong><?= e($profile['full_name']) ?></strong>
                <span class="library-card-label">STUDENT NUMBER</span>
                <strong><?= e($profile['student_no']) ?></strong>
                <span><?= e($profile['course']) ?></span>
              </div>
            </div>
          </article>
        </section>
      </main>
    </div>
<?php page_end(); ?>
