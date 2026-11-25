# PHINMA UPang School Library System

## Run locally with XAMPP

1. Start Apache and MySQL in the XAMPP Control Panel.
2. In phpMyAdmin, import `database.sql` to create the database and tables and add sample books for shared general education and introductory CS, IT, and Psychology courses. Re-importing updates the sample list without duplicating titles, upgrades existing staff-account table names without deleting accounts, and retires earlier generic samples rather than deleting them.
3. Open a terminal in this project folder and create the first admin:

   ```powershell
   C:\xampp\php\php.exe create_admin.php
   ```

   The password prompt is visible while typing. Use a password with at least 8 characters, including a letter and a number.
4. Open `http://localhost/upang-library-system/upang-library-system/` from the current workspace layout. The public landing page remains the first page; register a student account to use the student portal.

After signing in with the first admin account, use **Librarian accounts** in the admin sidebar to create additional librarian accounts without using the command line.

The default database connection is `127.0.0.1`, database `school_library_system`, user `root`, and a blank password, matching a standard local XAMPP installation. Set `LIBRARY_DB_HOST`, `LIBRARY_DB_NAME`, `LIBRARY_DB_USER`, and `LIBRARY_DB_PASSWORD` to override these values.

Students can register, update their profile, view their card, record library visits, request books, and request loan returns. Approved loans receive a due date 14 days after the borrow date. Admins can manage the inventory, approve or reject book requests, confirm returns, and review student records.

## Project file guide

| File or folder | Purpose |
| --- | --- |
| `index.html` | Public landing page; visitors can browse without logging in. |
| `register.php`, `login.php` | Student account creation and sign-in. |
| `dashboard.php`, `library-card.php` | Student profile editing and digital card. |
| `library-visits.php` | Student check-in, check-out, and visit history. |
| `borrowed-books.php` | Book requests, active loans, returns, and history. |
| `admin-login.php` | Admin sign-in. |
| `admin-accounts.php` | Create librarian accounts while signed in as an admin. |
| `admin-inventory.php` | Add, edit, and remove catalogue titles. |
| `admin-requests.php` | Approve or reject student book requests. |
| `admin-dashboard.php` | Confirm requested book returns. |
| `admin-records.php` | Search student library and borrowing records. |
| `includes/functions.php` | Shared database-page helpers, session checks, and page components. |
| `includes/dashboard_start.php` | Shared dashboard header and student/admin sidebar. |
| `config.php` | MySQL connection settings. |
| `database.sql` | Database and table definitions. |
| `assets/css/` and `assets/images/` | Existing front-end styles and image assets. |

5. Demo admin dashboard:
http://localhost/upang-library-system/upang-library-system/admin-login.php

Username: upang_demo_admin
Password: UpangDemo!2026