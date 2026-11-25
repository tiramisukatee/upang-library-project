<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
$student = require_student();
$error = '';

// Handle either a new book request or a return request from the student.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_value('action');

    try {
        if ($action === 'request') {
            $bookId = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$bookId) {
                throw new DomainException('Choose an available book.');
            }

            $connection = db();
            $connection->beginTransaction();
            // Serialize requests per student so concurrent submissions cannot exceed the 3-item limit.
            $studentLock = $connection->prepare('SELECT id FROM students WHERE id = :id FOR UPDATE');
            $studentLock->execute(['id' => $student['id']]);
            $countQuery = $connection->prepare(
                "SELECT
                    (SELECT COUNT(*) FROM loans WHERE student_id = :loan_student AND returned_at IS NULL) +
                    (SELECT COUNT(*) FROM book_requests
                     WHERE student_id = :request_student AND status = 'pending') AS total"
            );
            $countQuery->execute([
                'loan_student' => $student['id'],
                'request_student' => $student['id'],
            ]);
            if ((int) $countQuery->fetchColumn() >= 3) {
                throw new DomainException('You can have no more than 3 active loans and pending requests combined.');
            }

            $bookQuery = $connection->prepare(
                'SELECT id, copies_total FROM books
                 WHERE id = :id AND active = 1 FOR UPDATE'
            );
            $bookQuery->execute(['id' => $bookId]);
            $book = $bookQuery->fetch();
            $borrowedCount = 0;
            if ($book) {
                $borrowedQuery = $connection->prepare(
                    'SELECT COUNT(*) FROM loans WHERE book_id = :book_id AND returned_at IS NULL'
                );
                $borrowedQuery->execute(['book_id' => $bookId]);
                $borrowedCount = (int) $borrowedQuery->fetchColumn();
            }
            if (!$book || (int) $book['copies_total'] <= $borrowedCount) {
                throw new DomainException('That book is no longer available. Please choose another title.');
            }

            $insert = $connection->prepare(
                'INSERT INTO book_requests (student_id, book_id, due_date)
                 VALUES (:student_id, :book_id, DATE_ADD(CURDATE(), INTERVAL 14 DAY))'
            );
            $insert->execute([
                'student_id' => $student['id'],
                'book_id' => $bookId,
            ]);
            $connection->commit();
            flash('Your book request was sent to the admin.');
            redirect('borrowed-books.php');
        }

        if ($action === 'return') {
            // Only the owner of an active loan can ask staff to confirm its return.
            $loanId = filter_var($_POST['loan_id'] ?? null, FILTER_VALIDATE_INT);
            if (!$loanId) {
                throw new DomainException('The selected loan was not valid.');
            }
            $statement = db()->prepare(
                'UPDATE loans SET return_requested_at = NOW()
                 WHERE id = :id AND student_id = :student_id
                   AND returned_at IS NULL AND return_requested_at IS NULL'
            );
            $statement->execute(['id' => $loanId, 'student_id' => $student['id']]);
            if ($statement->rowCount() !== 1) {
                throw new DomainException('This loan cannot be marked for return.');
            }
            flash('Your return request was sent to the admin.');
            redirect('borrowed-books.php');
        }

        throw new DomainException('Choose a valid book action.');
    } catch (DomainException $exception) {
        if (isset($connection) && $connection->inTransaction()) {
            $connection->rollBack();
        }
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        if (isset($connection) && $connection->inTransaction()) {
            $connection->rollBack();
        }
        $error = database_error($exception);
    }
}

// Load the catalogue options, pending requests, active loans, and completed history.
try {
    $connection = db();
    $books = $connection->query(
        'SELECT b.id, b.title, b.author, b.copies_total -
                (SELECT COUNT(*) FROM loans l WHERE l.book_id = b.id AND l.returned_at IS NULL) AS available
         FROM books b
         WHERE b.active = 1 AND b.copies_total >
             (SELECT COUNT(*) FROM loans l WHERE l.book_id = b.id AND l.returned_at IS NULL)
         ORDER BY b.title'
    )->fetchAll();
    $requestsStatement = $connection->prepare(
        'SELECT r.id, b.title, r.status, r.requested_at
         FROM book_requests r JOIN books b ON b.id = r.book_id
         WHERE r.student_id = :student_id
         ORDER BY r.requested_at DESC'
    );
    $requestsStatement->execute(['student_id' => $student['id']]);
    $requests = $requestsStatement->fetchAll();
    $loansStatement = $connection->prepare(
        'SELECT l.id, b.title, l.borrowed_at, l.due_date, l.return_requested_at
         FROM loans l JOIN books b ON b.id = l.book_id
         WHERE l.student_id = :student_id AND l.returned_at IS NULL
         ORDER BY l.borrowed_at DESC'
    );
    $loansStatement->execute(['student_id' => $student['id']]);
    $loans = $loansStatement->fetchAll();
    $historyStatement = $connection->prepare(
        'SELECT b.title, l.borrowed_at, l.returned_at
         FROM loans l JOIN books b ON b.id = l.book_id
         WHERE l.student_id = :student_id AND l.returned_at IS NOT NULL
         ORDER BY l.returned_at DESC'
    );
    $historyStatement->execute(['student_id' => $student['id']]);
    $history = $historyStatement->fetchAll();
} catch (PDOException $exception) {
    $error = database_error($exception);
    $books = $requests = $loans = $history = [];
}

$title = 'Borrowed books';
$staff = false;
$active = 'books';
require __DIR__ . '/includes/dashboard_start.php';
?>
        <!-- Show request choices and the student's current borrowing limit. -->
        <h1 class="page-title">Borrowed books <span class="muted">(<?= count($loans) ?>/3)</span></h1>
        <?php render_flash(); ?>
        <?php if ($error !== ''): ?>
        <p class="alert alert-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <section class="panel dashboard-panel borrowed-books-panel">
          <h2>Request a book</h2>
          <form method="post" action="borrowed-books.php">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="request">
            <label>
              Book title
              <select name="book_id" required>
                <option value="" selected disabled>
                  <?= $books ? 'Select an available book' : 'No books are currently available.' ?>
                </option>
                <?php foreach ($books as $book): ?>
                <option value="<?= e($book['id']) ?>">
                  <?= e($book['title']) ?> — <?= e($book['author']) ?>
                  (<?= e($book['available']) ?> available)
                </option>
                <?php endforeach; ?>
              </select>
            </label>
            <p class="muted">Books can only be borrowed for 14 days. Your due date is set when your request is approved.</p>
            <button class="btn btn-green" type="submit"<?= $books ? '' : ' disabled' ?>>Request book</button>
          </form>
        </section>
        <!-- Requests remain visible here after admins approve or reject them. -->
        <section class="panel dashboard-panel">
          <h2>Book requests</h2>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th scope="col">Book</th>
                  <th scope="col">Requested on</th>
                  <th scope="col">Approval status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$requests): ?>
                <tr><td colspan="3">No book requests yet.</td></tr>
                <?php else: foreach ($requests as $request): ?>
                <tr>
                  <td><?= e($request['title']) ?></td>
                  <td><?= e(date('M j, Y', strtotime($request['requested_at']))) ?></td>
                  <td>
                    <span class="tag <?= $request['status'] === 'approved'
                        ? 'green'
                        : ($request['status'] === 'rejected' ? 'red' : 'yellow') ?>">
                      <?= e(ucfirst($request['status'])) ?>
                    </span>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </section>
        <!-- Active loans are cleared only after staff confirm the return. -->
        <section class="panel dashboard-panel">
          <h2>Currently borrowed</h2>
          <p class="muted">A loan is cleared from your account after an admin confirms the book return.</p>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th scope="col">Book</th>
                  <th scope="col">Date borrowed</th>
                  <th scope="col">Due date</th>
                  <th scope="col">Loan status</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$loans): ?>
                <tr><td colspan="4">No borrowed books yet.</td></tr>
                <?php else: foreach ($loans as $loan): ?>
                <tr>
                  <td><?= e($loan['title']) ?></td>
                  <td><?= e(date('M j, Y', strtotime($loan['borrowed_at']))) ?></td>
                  <td><?= e(date('M j, Y', strtotime($loan['due_date']))) ?></td>
                  <td>
                    <?php if ($loan['return_requested_at']): ?>
                    <span class="tag yellow">Return requested</span>
                    <?php else: ?>
                    <form method="post" action="borrowed-books.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="return">
                      <input type="hidden" name="loan_id" value="<?= e($loan['id']) ?>">
                      <button class="btn btn-small" type="submit">Request return</button>
                    </form>
                    <?php endif; ?>
                  </td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </section>
        <!-- Returned books remain in the student's borrowing history. -->
        <section class="panel dashboard-panel">
          <h2>Borrow history</h2>
          <p class="muted">Your borrowing and confirmed return dates appear here.</p>
          <div class="table-wrap">
            <table>
              <thead>
                <tr>
                  <th scope="col">Book</th>
                  <th scope="col">Date borrowed</th>
                  <th scope="col">Date returned</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$history): ?>
                <tr><td colspan="3">No records.</td></tr>
                <?php else: foreach ($history as $item): ?>
                <tr>
                  <td><?= e($item['title']) ?></td>
                  <td><?= e(date('M j, Y', strtotime($item['borrowed_at']))) ?></td>
                  <td><?= e(date('M j, Y', strtotime($item['returned_at']))) ?></td>
                </tr>
                <?php endforeach; endif; ?>
              </tbody>
            </table>
          </div>
        </section>
      </main>
    </div>
<?php page_end(); ?>
