<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
$admin = require_admin();
$error = '';

// Confirm only loans for which a student has already requested a return.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $loanId = filter_var($_POST['loan_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$loanId) {
        $error = 'The selected loan was not valid.';
    } else {
        try {
            $statement = db()->prepare(
                'UPDATE loans SET returned_at = NOW()
                 WHERE id = :id AND returned_at IS NULL AND return_requested_at IS NOT NULL'
            );
            $statement->execute(['id' => $loanId]);
            if ($statement->rowCount() !== 1) {
                $error = 'This return is no longer awaiting confirmation.';
            } else {
                flash('The book return was confirmed.');
                redirect('admin-dashboard.php');
            }
        } catch (PDOException $exception) {
            $error = database_error($exception);
        }
    }
}

// Show all return requests that still need admin review.
try {
    $returns = db()->query(
        'SELECT l.id, l.return_requested_at, s.student_no, s.full_name, b.title
         FROM loans l
         JOIN students s ON s.id = l.student_id
         JOIN books b ON b.id = l.book_id
         WHERE l.return_requested_at IS NOT NULL AND l.returned_at IS NULL
         ORDER BY l.return_requested_at'
    )->fetchAll();
} catch (PDOException $exception) {
    $error = database_error($exception);
    $returns = [];
}

// This page is the admin's return-confirmation queue.
$title = 'Admin dashboard';
$staff = true;
$active = 'returns';
require __DIR__ . '/includes/dashboard_start.php';
?>
        <h1 class="page-title">Book returns</h1>
        <p class="muted">Review returned books and confirm them to clear a student's loan status.</p>
        <?php render_flash(); ?>
        <?php if ($error !== ''): ?>
        <p class="alert alert-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <section class="panel dashboard-panel admin-records" aria-labelledby="return-confirmation-heading">
          <div class="records-toolbar">
            <div>
              <h2 id="return-confirmation-heading">Book returns awaiting confirmation</h2>
              <p class="muted">Confirm a returned copy before the student's loan status is cleared.</p>
            </div>
          </div>
          <div class="table-wrap">
            <table class="student-record-table">
              <thead>
                <tr>
                  <th scope="col">Student ID</th>
                  <th scope="col">Student</th>
                  <th scope="col">Book</th>
                  <th scope="col">Return requested</th>
                  <th scope="col">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$returns): ?>
                <tr><td colspan="5">No returns awaiting confirmation.</td></tr>
                <?php else: foreach ($returns as $return): ?>
                <tr>
                  <td><?= e($return['student_no']) ?></td>
                  <td><?= e($return['full_name']) ?></td>
                  <td><?= e($return['title']) ?></td>
                  <td><?= e(date('M j, Y g:i A', strtotime($return['return_requested_at']))) ?></td>
                  <td>
                    <form method="post" action="admin-dashboard.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="loan_id" value="<?= e($return['id']) ?>">
                      <button class="btn btn-small" type="submit">Confirm return</button>
                    </form>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </section>
      </main>
    </div>
<?php page_end(); ?>
