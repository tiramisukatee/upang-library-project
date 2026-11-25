<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
$admin = require_admin();
$error = '';

// Approve or reject a pending request; approval also creates the active loan.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $requestId = filter_var($_POST['request_id'] ?? null, FILTER_VALIDATE_INT);
    $action = post_value('action');
    if (!$requestId || !in_array($action, ['approve', 'reject'], true)) {
        $error = 'Choose a valid book request action.';
    } else {
        $connection = null;
        try {
            $connection = db();
            $connection->beginTransaction();
            $requestQuery = $connection->prepare(
                'SELECT id, student_id, book_id, status
                 FROM book_requests WHERE id = :id FOR UPDATE'
            );
            $requestQuery->execute(['id' => $requestId]);
            $request = $requestQuery->fetch();
            if (!$request || $request['status'] !== 'pending') {
                throw new DomainException('This request is no longer awaiting approval.');
            }

            if ($action === 'approve') {
                // Lock the student and book before checking limits and recording the loan.
                $studentLock = $connection->prepare(
                    'SELECT id FROM students WHERE id = :id FOR UPDATE'
                );
                $studentLock->execute(['id' => $request['student_id']]);
                $bookQuery = $connection->prepare(
                    'SELECT id, active, copies_total FROM books WHERE id = :id FOR UPDATE'
                );
                $bookQuery->execute(['id' => $request['book_id']]);
                $book = $bookQuery->fetch();
                $borrowedCount = 0;
                if ($book) {
                    $borrowedQuery = $connection->prepare(
                        'SELECT COUNT(*) FROM loans WHERE book_id = :book_id AND returned_at IS NULL'
                    );
                    $borrowedQuery->execute(['book_id' => $request['book_id']]);
                    $borrowedCount = (int) $borrowedQuery->fetchColumn();
                }
                $loanCount = $connection->prepare(
                    'SELECT COUNT(*) FROM loans WHERE student_id = :student_id AND returned_at IS NULL'
                );
                $loanCount->execute(['student_id' => $request['student_id']]);

                if (
                    !$book || !(int) $book['active'] ||
                    (int) $book['copies_total'] <= $borrowedCount ||
                    (int) $loanCount->fetchColumn() >= 3
                ) {
                    throw new DomainException(
                        'The book is unavailable or the student ' .
                        'already has 3 active loans. Reject this request instead.'
                    );
                }

                $loanInsert = $connection->prepare(
                    'INSERT INTO loans (request_id, student_id, book_id, borrowed_at, due_date)
                     VALUES (:request_id, :student_id, :book_id, NOW(),
                             DATE(DATE_ADD(NOW(), INTERVAL 14 DAY)))'
                );
                $loanInsert->execute([
                    'request_id' => $request['id'],
                    'student_id' => $request['student_id'],
                    'book_id' => $request['book_id'],
                ]);
            }

            $update = $connection->prepare(
                'UPDATE book_requests
                 SET status = :status, processed_at = NOW(), admin_id = :admin_id
                 WHERE id = :id'
            );
            $update->execute([
                'status' => $action === 'approve' ? 'approved' : 'rejected',
                'admin_id' => $admin['id'],
                'id' => $requestId,
            ]);
            $connection->commit();
            flash($action === 'approve' ? 'The request was approved.' : 'The request was rejected.');
            redirect('admin-requests.php');
        } catch (DomainException $exception) {
            if ($connection && $connection->inTransaction()) {
                $connection->rollBack();
            }
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($connection && $connection->inTransaction()) {
                $connection->rollBack();
            }
            $error = database_error($exception);
        }
    }
}

// Only pending requests are shown in the staff review queue.
try {
    $requests = db()->query(
        "SELECT r.id, r.requested_at, s.student_no, s.full_name, b.title
         FROM book_requests r
         JOIN students s ON s.id = r.student_id
         JOIN books b ON b.id = r.book_id
         WHERE r.status = 'pending'
         ORDER BY r.requested_at"
    )->fetchAll();
} catch (PDOException $exception) {
    $error = database_error($exception);
    $requests = [];
}

$title = 'Book requests';
$staff = true;
$active = 'requests';
require __DIR__ . '/includes/dashboard_start.php';
?>
        <!-- Each row identifies the student, requested title, request time, and staff actions. -->
        <h1 class="page-title">Book requests</h1>
        <p class="muted">Review student requests and approve or reject each one.</p>
        <?php render_flash(); ?>
        <?php if ($error !== ''): ?>
        <p class="alert alert-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <section class="panel dashboard-panel admin-records">
          <div class="records-toolbar">
            <div>
              <h2>Requests awaiting approval</h2>
              <p class="muted">
                Approval reserves an available copy from Book Records and adds it
                to the student's active loans.
              </p>
            </div>
          </div>
          <div class="table-wrap">
            <table class="student-record-table">
              <thead>
                <tr>
                  <th scope="col">Student ID</th>
                  <th scope="col">Student</th>
                  <th scope="col">Book</th>
                  <th scope="col">Requested on</th>
                  <th scope="col">Requested at</th>
                  <th scope="col">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$requests): ?>
                <tr><td colspan="6">No requests awaiting approval.</td></tr>
                <?php else: foreach ($requests as $request): ?>
                <tr>
                  <td><?= e($request['student_no']) ?></td>
                  <td><?= e($request['full_name']) ?></td>
                  <td><?= e($request['title']) ?></td>
                  <td><?= e(date('M j, Y g:i A', strtotime($request['requested_at']))) ?></td>
                  <td><?= e(date('M j, Y g:i A', strtotime($request['requested_at']))) ?></td>
                  <td class="inventory-actions">
                    <?php foreach (['approve' => 'Approve', 'reject' => 'Reject'] as $action => $label): ?>
                    <form method="post" action="admin-requests.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="request_id" value="<?= e($request['id']) ?>">
                      <input type="hidden" name="action" value="<?= e($action) ?>">
                      <button
                        class="btn btn-small<?= $action === 'reject' ? ' btn-danger' : '' ?>"
                        type="submit"
                      >
                        <?= e($label) ?>
                      </button>
                    </form>
                    <?php endforeach; ?>
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
