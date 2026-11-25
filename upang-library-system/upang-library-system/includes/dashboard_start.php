<?php
// Shared dashboard layout. Pages set their title, role, and active sidebar item first.
$classes = trim(($staff ? 'admin-page ' : 'student-page ') . ($bodyClass ?? ''));
page_start($title, $classes, 'dashboard');
?>
    <div class="dashboard-layout">
      <?php
      if ($staff) {
          admin_sidebar($active);
      } else {
          student_sidebar($active);
      }
      ?>
      <main class="dashboard-main">
