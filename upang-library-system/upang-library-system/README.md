# PHINMA UPang School Library System

## Run locally with XAMPP

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
