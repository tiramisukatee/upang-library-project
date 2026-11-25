<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_admin();
$error = '';
$editBook = null;

// Process add, edit, and remove actions submitted from the inventory screen.
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_value('action');
    $bookId = filter_var($_POST['book_id'] ?? null, FILTER_VALIDATE_INT);

    try {
        if ($action === 'remove' && $bookId) {
            $statement = db()->prepare('UPDATE books SET active = 0 WHERE id = :id AND active = 1');
            $statement->execute(['id' => $bookId]);
            if ($statement->rowCount() !== 1) {
                throw new DomainException('This book is already removed or was not found.');
            }
            flash('The book was removed from the available inventory.');
            redirect('admin-inventory.php');
        }

        if (!in_array($action, ['add', 'update'], true)) {
            throw new DomainException('Choose a valid inventory action.');
        }
        $titleValue = post_value('title');
        $author = post_value('author');
        $copies = filter_var($_POST['copies'] ?? null, FILTER_VALIDATE_INT);
        if (
            $titleValue === '' || strlen($titleValue) > 120 ||
            $author === '' || strlen($author) > 100 ||
            !$copies || $copies > 10000 || ($action === 'update' && !$bookId)
        ) {
            throw new DomainException('Enter a title, author, and a whole number of copies from 1 to 10,000.');
        }

        $connection = db();
        if ($action === 'add') {
            $statement = $connection->prepare(
                'INSERT INTO books (title, author, copies_total)
                 VALUES (:title, :author, :copies)
                 ON DUPLICATE KEY UPDATE
                    copies_total = copies_total + VALUES(copies_total), active = 1'
            );
            $statement->execute(['title' => $titleValue, 'author' => $author, 'copies' => $copies]);
            flash('The book was added to the inventory.');
        } else {
            // Prevent reducing the copy count below the number currently on loan.
            $connection->beginTransaction();
            $lock = $connection->prepare('SELECT id FROM books WHERE id = :id AND active = 1 FOR UPDATE');
            $lock->execute(['id' => $bookId]);
            if (!$lock->fetch()) {
                throw new DomainException('This book is no longer in the inventory.');
            }
            $activeLoans = $connection->prepare(
                'SELECT COUNT(*) FROM loans WHERE book_id = :book_id AND returned_at IS NULL'
            );
            $activeLoans->execute(['book_id' => $bookId]);
            if ($copies < (int) $activeLoans->fetchColumn()) {
                throw new DomainException('Total copies cannot be lower than the number currently borrowed.');
            }
            $statement = $connection->prepare(
                'UPDATE books SET title = :title, author = :author, copies_total = :copies
                 WHERE id = :id'
            );
            $statement->execute([
                'title' => $titleValue,
                'author' => $author,
                'copies' => $copies,
                'id' => $bookId,
            ]);
            $connection->commit();
            flash('The book record was updated.');
        }
        redirect('admin-inventory.php');
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
        if ((string) $exception->getCode() === '23000') {
            $error = 'A book with that title and author already exists.';
        }
    }
}

// Load active books and, when requested, the selected row for editing.
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
try {
    $connection = db();
    $books = $connection->query(
        'SELECT b.id, b.title, b.author, b.copies_total,
                b.copies_total - COUNT(l.id) AS available
         FROM books b
         LEFT JOIN loans l ON l.book_id = b.id AND l.returned_at IS NULL
         WHERE b.active = 1
         GROUP BY b.id, b.title, b.author, b.copies_total
         ORDER BY b.title'
    )->fetchAll();
    if ($editId) {
        $statement = $connection->prepare(
            'SELECT id, title, author, copies_total FROM books WHERE id = :id AND active = 1'
        );
        $statement->execute(['id' => $editId]);
        $editBook = $statement->fetch() ?: null;
    }
} catch (PDOException $exception) {
    $error = database_error($exception);
    $books = [];
}

$title = 'Book Records';
$staff = true;
$active = 'inventory';
require __DIR__ . '/includes/dashboard_start.php';
?>
        <!-- The same form is used to add a title or update the selected record. -->
        <h1 class="page-title">Book Records</h1>
        <p class="muted">Add books available for student requests or remove titles the library no longer holds.</p>
        <?php render_flash(); ?>
        <?php if ($error !== ''): ?>
        <p class="alert alert-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <section class="panel dashboard-panel">
          <h2><?= $editBook ? 'Edit book' : 'Add books' ?></h2>
          <form method="post" action="admin-inventory.php" class="inventory-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editBook ? 'update' : 'add' ?>">
            <?php if ($editBook): ?>
            <input type="hidden" name="book_id" value="<?= e($editBook['id']) ?>">
            <?php endif; ?>
            <label>
              Book title
              <input name="title" required maxlength="120" value="<?= e($editBook['title'] ?? '') ?>">
            </label>
            <label>
              Author
              <input name="author" required maxlength="100" value="<?= e($editBook['author'] ?? '') ?>">
            </label>
            <label>
              Number of copies
              <input
                name="copies"
                type="number"
                required
                min="1"
                max="10000"
                step="1"
                value="<?= e($editBook['copies_total'] ?? '') ?>"
              >
            </label>
            <div class="inventory-form-actions">
              <button class="btn btn-green" type="submit"><?= $editBook ? 'Save changes' : 'Add books' ?></button>
              <?php if ($editBook): ?>
              <a class="btn btn-small" href="admin-inventory.php">Cancel</a>
              <?php endif; ?>
            </div>
          </form>
        </section>
        <section class="panel dashboard-panel admin-records">
          <div class="records-toolbar">
            <div>
              <h2>Available books</h2>
              <p class="muted"><?= count($books) ?> titles</p>
            </div>
          </div>
          <div class="table-wrap">
            <table class="student-record-table">
              <thead>
                <tr>
                  <th scope="col">Book title</th>
                  <th scope="col">Author</th>
                  <th scope="col">Total copies</th>
                  <th scope="col">Available</th>
                  <th scope="col">Action</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$books): ?>
                <tr><td colspan="5">No records.</td></tr>
                <?php else: foreach ($books as $book): ?>
                <tr>
                  <td><?= e($book['title']) ?></td>
                  <td><?= e($book['author']) ?></td>
                  <td><?= e($book['copies_total']) ?></td>
                  <td><?= e($book['available']) ?></td>
                  <td class="inventory-actions">
                    <a class="btn btn-small" href="admin-inventory.php?edit=<?= e($book['id']) ?>">Edit</a>
                    <form method="post" action="admin-inventory.php">
                      <?= csrf_field() ?>
                      <input type="hidden" name="action" value="remove">
                      <input type="hidden" name="book_id" value="<?= e($book['id']) ?>">
                      <button class="btn btn-small btn-danger" type="submit">Remove</button>
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
