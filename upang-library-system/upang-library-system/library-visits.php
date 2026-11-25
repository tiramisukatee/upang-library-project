<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
$student = require_student();
$error = '';

// A POST switches the student's state between checked in and checked out.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $connection = db();
        $connection->beginTransaction();
        // Lock the student row so two simultaneous check-ins cannot create two open visits.
        $lock = $connection->prepare('SELECT id FROM students WHERE id = :id FOR UPDATE');
        $lock->execute(['id' => $student['id']]);
        $openQuery = $connection->prepare(
            'SELECT id FROM library_visits
             WHERE student_id = :student_id AND checked_out_at IS NULL
             ORDER BY checked_in_at DESC LIMIT 1 FOR UPDATE'
        );
        $openQuery->execute(['student_id' => $student['id']]);
        $openVisit = $openQuery->fetch();

        if ($openVisit) {
            $statement = $connection->prepare(
                'UPDATE library_visits SET checked_out_at = NOW() WHERE id = :id'
            );
            $statement->execute(['id' => $openVisit['id']]);
            $message = 'You have checked out of the library.';
        } else {
            $statement = $connection->prepare(
                'INSERT INTO library_visits (student_id) VALUES (:student_id)'
            );
            $statement->execute(['student_id' => $student['id']]);
            $message = 'You have checked in to the library.';
        }

        $connection->commit();
        flash($message);
        redirect('library-visits.php');
    } catch (PDOException $exception) {
        if (isset($connection) && $connection->inTransaction()) {
            $connection->rollBack();
        }
        $error = database_error($exception);
    }
}

// Retrieve the latest ten visits and identify any visit that is still open.
try {
    $statement = db()->prepare(
        'SELECT id, checked_in_at, checked_out_at FROM library_visits
         WHERE student_id = :student_id ORDER BY checked_in_at DESC LIMIT 10'
    );
    $statement->execute(['student_id' => $student['id']]);
    $visits = $statement->fetchAll();
    $openVisit = null;
    foreach ($visits as $visit) {
        if ($visit['checked_out_at'] === null) {
            $openVisit = $visit;
            break;
        }
    }
} catch (PDOException $exception) {
    $error = database_error($exception);
    $visits = [];
    $openVisit = null;
}

// Render the visit toggle and recent visit history in the student dashboard.
$title = 'Library visits';
$staff = false;
$active = 'visits';
require __DIR__ . '/includes/dashboard_start.php';
?>
        <h1 class="page-title">Check in / out &amp; recent visits</h1>
        <section class="panel dashboard-panel visit-page-panel" id="library-visit">
          <h2>Check in / out</h2>
          <p>Current status:
            <?php if ($openVisit): ?>
            <span class="status in">Checked in</span>
            <?php else: ?>
            <span class="status out">Checked out</span>
            <?php endif; ?>
          </p>
          <p class="muted">
            <?php if ($openVisit): ?>
            Checked in at <?= e(date('M j, Y g:i A', strtotime($openVisit['checked_in_at']))) ?>.
            <?php else: ?>
            You are currently outside the library.
            <?php endif; ?>
          </p>
          <?php render_flash(); ?>
          <?php if ($error !== ''): ?>
          <p class="alert alert-error" role="alert"><?= e($error) ?></p>
          <?php endif; ?>
          <form method="post" action="library-visits.php">
            <?= csrf_field() ?>
            <button class="btn btn-green" type="submit"><?= $openVisit ? 'Check out' : 'Check in' ?></button>
          </form>
        </section>
        <section class="panel dashboard-panel visit-history" id="recent-visits">
          <h2>Recent visits</h2>
          <?php if ($visits): ?>
          <ul class="history-list">
            <?php foreach ($visits as $visit): ?>
            <li>
              Checked in <?= e(date('M j, Y g:i A', strtotime($visit['checked_in_at']))) ?>
              <?php if ($visit['checked_out_at']): ?>
              &mdash; checked out <?= e(date('M j, Y g:i A', strtotime($visit['checked_out_at']))) ?>
              <?php else: ?>
              &mdash; currently in the library
              <?php endif; ?>
            </li>
            <?php endforeach; ?>
          </ul>
          <?php else: ?>
          <p>No library visits recorded yet.</p>
          <?php endif; ?>
        </section>
      </main>
    </div>
<?php page_end(); ?>
