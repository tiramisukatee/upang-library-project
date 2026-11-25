<?php
declare(strict_types=1);

require_once __DIR__ . '/includes/functions.php';
require_admin();
$search = trim((string) ($_GET['search'] ?? ''));
$error = '';

// Search student identity, current loans, and current library visit status.
try {
    $statement = db()->prepare(
        'SELECT s.student_no, s.full_name, s.course, s.email,
                (SELECT COUNT(*) FROM loans l WHERE l.student_id = s.id AND l.returned_at IS NULL) AS borrowed_count,
                (SELECT GROUP_CONCAT(b.title ORDER BY b.title SEPARATOR ", ")
                 FROM loans l JOIN books b ON b.id = l.book_id
                 WHERE l.student_id = s.id AND l.returned_at IS NULL) AS borrowed_titles,
                (SELECT v.checked_in_at FROM library_visits v
                 WHERE v.student_id = s.id AND v.checked_out_at IS NULL
                 ORDER BY v.checked_in_at DESC LIMIT 1) AS checked_in_at
         FROM students s
         WHERE :search = ""
            OR s.student_no LIKE :student_search
            OR s.full_name LIKE :name_search
            OR s.course LIKE :course_search
            OR s.email LIKE :email_search
         ORDER BY s.full_name'
    );
    $pattern = '%' . $search . '%';
    $statement->execute([
        'search' => $search,
        'student_search' => $pattern,
        'name_search' => $pattern,
        'course_search' => $pattern,
        'email_search' => $pattern,
    ]);
    $students = $statement->fetchAll();
} catch (PDOException $exception) {
    $error = database_error($exception);
    $students = [];
}

$title = 'Student records';
$staff = true;
$active = 'records';
require __DIR__ . '/includes/dashboard_start.php';
?>
        <!-- The search term filters the directory without changing student records. -->
        <h1 class="page-title">Student records</h1>
        <p class="muted">Review library visit status and currently borrowed books.</p>
        <?php if ($error !== ''): ?>
        <p class="alert alert-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <section class="panel dashboard-panel admin-records">
          <div class="records-toolbar">
            <div>
              <h2>Student directory</h2>
              <p class="muted"><?= count($students) ?> students</p>
            </div>
          </div>
          <form method="get" action="admin-records.php">
            <label class="records-search">
              Search by student ID, name, course, or email
              <input
                name="search"
                type="search"
                autocomplete="off"
                placeholder="Search student records"
                value="<?= e($search) ?>"
              >
            </label>
            <button class="btn btn-outline records-refresh" type="submit">Search records</button>
            <?php if ($search !== ''): ?>
            <a class="btn btn-small" href="admin-records.php">Clear search</a>
            <?php endif; ?>
          </form>
          <div class="table-wrap">
            <table class="student-record-table">
              <thead>
                <tr>
                  <th scope="col">Student ID</th>
                  <th scope="col">Student</th>
                  <th scope="col">Course / program</th>
                  <th scope="col">Library status</th>
                  <th scope="col">Borrowed books</th>
                </tr>
              </thead>
              <tbody>
                <?php if (!$students): ?>
                <tr>
                  <td colspan="5">
                    <?= $search === '' ? 'No student records.' : 'No students matched your search.' ?>
                  </td>
                </tr>
                <?php else: foreach ($students as $record): ?>
                <tr>
                  <td>
                    <?= e($record['student_no']) ?>
                    <span class="record-secondary"><?= e($record['email']) ?></span>
                  </td>
                  <td><?= e($record['full_name']) ?></td>
                  <td><?= e($record['course']) ?></td>
                  <td>
                    <?php if ($record['checked_in_at']): ?>
                    <span class="tag green">In library</span>
                    <?php else: ?>
                    <span class="tag neutral">Checked out</span>
                    <?php endif; ?>
                  </td>
                  <td>
                    <?= e($record['borrowed_count']) ?>
                    <?php if ($record['borrowed_titles']): ?>
                    <span class="record-secondary"><?= e($record['borrowed_titles']) ?></span>
                    <?php endif; ?>
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
