-- Create the local application database before adding its tables.
CREATE DATABASE IF NOT EXISTS school_library_system
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE school_library_system;

-- Rename staff accounts and request links for installations using the previous names.
SET @rename_admin_table = IF(
  (SELECT COUNT(*) FROM information_schema.tables
   WHERE table_schema = DATABASE() AND table_name = 'librarians') = 1
  AND (SELECT COUNT(*) FROM information_schema.tables
       WHERE table_schema = DATABASE() AND table_name = 'admins') = 0,
  'RENAME TABLE librarians TO admins',
  'SELECT 1'
);
PREPARE rename_admin_table_stmt FROM @rename_admin_table;
EXECUTE rename_admin_table_stmt;
DEALLOCATE PREPARE rename_admin_table_stmt;

SET @rename_admin_column = IF(
  (SELECT COUNT(*) FROM information_schema.columns
   WHERE table_schema = DATABASE() AND table_name = 'book_requests'
     AND column_name = 'librarian_id') = 1
  AND (SELECT COUNT(*) FROM information_schema.columns
       WHERE table_schema = DATABASE() AND table_name = 'book_requests'
         AND column_name = 'admin_id') = 0,
  'ALTER TABLE book_requests DROP FOREIGN KEY fk_request_librarian, CHANGE COLUMN librarian_id admin_id INT UNSIGNED DEFAULT NULL, ADD CONSTRAINT fk_request_admin FOREIGN KEY (admin_id) REFERENCES admins(id)',
  'SELECT 1'
);
PREPARE rename_admin_column_stmt FROM @rename_admin_column;
EXECUTE rename_admin_column_stmt;
DEALLOCATE PREPARE rename_admin_column_stmt;

-- Student login details and the profile shown on the digital library card.
CREATE TABLE IF NOT EXISTS students (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_no VARCHAR(20) NOT NULL UNIQUE,
  full_name VARCHAR(100) NOT NULL,
  course VARCHAR(60) NOT NULL,
  email VARCHAR(120) NOT NULL UNIQUE,
  contact_number CHAR(10) NOT NULL,
  address VARCHAR(250) NOT NULL,
  -- PHP password_hash() output; the plain-text password is never stored.
  password_hash VARCHAR(255) NOT NULL,
  profile_picture VARCHAR(255) DEFAULT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Staff login accounts; passwords are stored as PHP-generated hashes.
CREATE TABLE IF NOT EXISTS admins (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  username VARCHAR(60) NOT NULL UNIQUE,
  password_hash VARCHAR(255) NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- Library catalogue. Inactive rows are retained when staff remove a title.
CREATE TABLE IF NOT EXISTS books (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  title VARCHAR(120) NOT NULL,
  author VARCHAR(100) NOT NULL,
  -- Available copies are calculated by subtracting active loans.
  copies_total INT UNSIGNED NOT NULL,
  -- Staff removal hides a title without breaking historical loan records.
  active TINYINT(1) NOT NULL DEFAULT 1,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY unique_book_title_author (title, author)
) ENGINE=InnoDB;

-- Student requests stay here after review so approval history is preserved.
CREATE TABLE IF NOT EXISTS book_requests (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  book_id INT UNSIGNED NOT NULL,
  due_date DATE NOT NULL,
  -- Pending requests await staff; processed requests remain for history.
  status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
  requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  processed_at DATETIME DEFAULT NULL,
  admin_id INT UNSIGNED DEFAULT NULL,
  CONSTRAINT fk_request_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_request_book FOREIGN KEY (book_id) REFERENCES books(id),
  CONSTRAINT fk_request_admin FOREIGN KEY (admin_id) REFERENCES admins(id),
  INDEX idx_request_status (status, requested_at),
  INDEX idx_request_student (student_id)
) ENGINE=InnoDB;

-- An approved request creates a loan; return timestamps track its lifecycle.
CREATE TABLE IF NOT EXISTS loans (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  -- A request can produce at most one loan.
  request_id INT UNSIGNED NOT NULL UNIQUE,
  student_id INT UNSIGNED NOT NULL,
  book_id INT UNSIGNED NOT NULL,
  borrowed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  due_date DATE NOT NULL,
  -- Student requests a return first; an admin sets returned_at to confirm it.
  return_requested_at DATETIME DEFAULT NULL,
  returned_at DATETIME DEFAULT NULL,
  CONSTRAINT fk_loan_request FOREIGN KEY (request_id) REFERENCES book_requests(id),
  CONSTRAINT fk_loan_student FOREIGN KEY (student_id) REFERENCES students(id),
  CONSTRAINT fk_loan_book FOREIGN KEY (book_id) REFERENCES books(id),
  INDEX idx_loan_student_active (student_id, returned_at)
) ENGINE=InnoDB;

-- One row per library visit; a null checkout time means the visit is still open.
CREATE TABLE IF NOT EXISTS library_visits (
  id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  student_id INT UNSIGNED NOT NULL,
  checked_in_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  checked_out_at DATETIME DEFAULT NULL,
  CONSTRAINT fk_visit_student FOREIGN KEY (student_id) REFERENCES students(id),
  INDEX idx_visit_student_open (student_id, checked_out_at)
) ENGINE=InnoDB;

-- Retire the earlier generic sample titles without deleting their history.
UPDATE books
SET active = 0
WHERE author = 'OpenStax'
  AND title IN (
    'College Success',
    'Introduction to Sociology 3e',
    'Psychology 2e',
    'World History, Volume 1: To 1500',
    'Biology 2e',
    'Principles of Economics 3e',
    'Writing Guide with Handbook'
  );

-- Seed shared general-education and introductory CS/IT/Psychology references.
-- Re-importing keeps the larger copy count and reactivates these sample titles.
INSERT INTO books (title, author, copies_total) VALUES
  ('College Algebra 2e', 'Jay Abramson (OpenStax)', 4),
  ('Writing Guide with Handbook', 'OpenStax', 4),
  ('Psychology 2e', 'Rose M. Spielman, William J. Jenkins, and Marilyn D. Lovett', 4),
  ('Introduction to Sociology 3e', 'Heather Griffiths et al. (OpenStax)', 4),
  ('World History, Volume 1: To 1500', 'Robert W. Strayer and Eric W. Nelson (OpenStax)', 4),
  ('Biology 2e', 'Mary Ann Clark, Jung Choi, and Matthew Douglas (OpenStax)', 4),
  ('Principles of Economics 3e', 'Steven A. Greenlaw and David Shapiro (OpenStax)', 4),
  ('Computer Science: An Overview, 13th Edition', 'J. Glenn Brookshear and Dennis Brylow', 3),
  ('Starting Out with Programming Logic and Design', 'Tony Gaddis', 3),
  ('Think Python, 2nd Edition', 'Allen B. Downey', 3),
  ('College Success', 'Amy Baldwin (OpenStax)', 4)
ON DUPLICATE KEY UPDATE
  copies_total = GREATEST(copies_total, VALUES(copies_total)),
  active = 1;
