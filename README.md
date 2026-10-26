# College ERP Management System

A locally hosted, server-rendered College ERP for PHP 8+ and MySQL/MariaDB. It is designed for XAMPP on Windows, with tenant boundaries between the university's colleges. Bootstrap 5.3.3 CSS/JS are included locally in `public/assets`, so the interface does not require a CDN or an internet connection after installation.

## Requirements

- XAMPP with PHP 8.0+ and MySQL/MariaDB (PDO MySQL enabled).
- A modern browser.
- No Composer/npm packages or external runtime services.

## Install on XAMPP

1. Copy this project directory to `C:\xampp\htdocs\ERP` (or another Apache document-root folder).
2. Start **Apache** and **MySQL** from the XAMPP Control Panel.
3. In phpMyAdmin (`http://localhost/phpmyadmin`) create an empty database named `college_erp`, using `utf8mb4` if prompted.
4. Import `database/schema.sql` into that empty database. It creates the tables, two sample colleges, and fictional non-credential records for the module workflows. **The SQL file drops/recreates the ERP tables, so import only into a new, empty ERP database.**
5. Copy `config/config.example.php` to `config/config.php`. Edit the `db` section for your local MySQL host, database, username, and password. This local config is excluded by `.gitignore`; never put a real password into `config.example.php`.
6. Open `http://localhost/ERP/`. The first-run wizard creates the initial Super Admin. Use a unique email and a strong password of at least 12 characters containing letters, a number, and a symbol. There are no seeded login accounts or demo passwords.
7. Sign in and use **User accounts** to create accounts for University Admins, College Admins, Teachers, Students, and Parents. Link teacher accounts to a staff record; link student accounts to the student's record; link parent accounts to one or more student records.
8. Add real university and college records, users, academic data, and operational records as appropriate. Sample rows are examples only.

### Local portal accounts

These credentials are for the current local XAMPP database only:

Login page: `http://localhost/ERP/`



These are weak, shared local-development credentials. Do not deploy or expose
this README or these accounts to the internet. Change every password after
sign-in, and remove the password column before sharing or publishing this
project. If a password is changed or forgotten, use the recovery instructions
below.

### Configuration

The supported local configuration is `config/config.php`, returning:

```php
return [
    'db' => ['host' => '127.0.0.1', 'port' => 3306, 'name' => 'college_erp', 'user' => 'root', 'pass' => ''],
    'app' => ['base_url' => '/ERP', 'session_name' => 'college_erp_session'],
];
```

Set `base_url` to the application URL prefix (`/ERP` for the default XAMPP folder; use an empty string if served at the web root). XAMPP's default MySQL root account may have an empty password; configure your local installation accordingly. For a shared or production environment, use a least-privilege database account, HTTPS, managed secrets, backups, and a security review. Production deployment is outside this local-XAMPP release.

## One-time Super Admin setup

`index.php?action=setup` is open only until the first Super Admin account is created. The setup handler validates the submitted name, email and password, hashes the password with PHP's password API, serializes first-run creation with a database lock, and refuses additional setup after completion. Do not clear the first administrator from the database to reset the wizard. Use normal account/password administration instead.

### Recovering administrator email or password locally

If an administrator cannot sign in or does not know which email is on the
account, open a local terminal in the project folder and run:

```powershell
C:\xampp\php\php.exe .\tools\recover-admin-login.php
```

The local CLI utility lists administrator names, roles, and registered emails.
Select the account, optionally enter a corrected email, and set a new password.
It supports Super Admin, University Admin, and College Admin accounts, clears
the selected account's failed-login lockout, and can reactivate an inactive
account only after an explicit confirmation. Password input is visible in the
terminal; run it only where others cannot see your screen. The utility is
CLI-only; web requests to it are rejected. Never share the password in chat or
put it in a command-line argument.

The existing `reset-super-admin-password.php` utility remains available if the
Super Admin email is already known and only its password needs to be reset.

### Resetting other account passwords

A signed-in Super Admin, University Admin, or College Admin can reset passwords
for accounts listed within their permitted scope. Open **User accounts**, use
**Reset password** beside the account, enter and confirm a new password (at
least 12 characters with letters, a number, and a symbol), then share it with
the account holder through a private channel. This clears that account's
failed-login lockout. Admins cannot reset their own password or another Super
Admin's password through this screen; use **Change password** for your own
account. No password reset email is sent.

## Included workflows

- Authentication, logout, own-password change, failed-login lockout, user activation/deactivation, CSRF-protected state-changing forms, and role-aware access.
- Administrators can reset passwords for accounts within their scope from **User accounts**. The new password is set directly (no recovery email is sent); share it privately with the account holder. Super Admin passwords are excluded from this screen and use the CLI-only recovery tool.
- University/college directory, admissions review and accepted-application enrollment, student/staff accounts and roster records.
- Academic years, classes, sections, subjects and teacher assignments.
- Daily attendance, fee types/charges, transactional payments with outstanding-balance checks and printable receipt pages.
- Exams, marks, calculated per-subject and exam-total grades (A ≥ 90%, B ≥ 80%, C ≥ 70%, D ≥ 60%, otherwise F), publish/unpublish control, and publication-gated student/parent results.
- Library book catalogue and issue/return tracking with copy availability checks; hostel room/student allocation with capacity checks; transport routes, vehicles, and student assignments with capacity checks.
- Live, tenant-scoped dashboards with enrollment trends, attendance summaries, fee collection, admissions, upcoming examinations, student-by-class counts, and role-aware quick links. Date-filtered reports cover admissions, rosters, attendance, fees, marks, library, hostel, and transport. Reports and receipts have print-friendly views.

All module records use server-side validation and prepared statements. University Admins choose a college within their university for college-scoped writes. College Admins and Teachers are scoped to their college; Teachers can revise attendance/marks only for assigned class/subject records. Student and Parent accounts are read-only and limited to the student profile/linked student records, with unpublished results hidden. These controls are enforced on server-side queries and writes, not only in navigation.

## Operations and troubleshooting

- If the database page reports a connection error, verify MySQL is running and the values in `config/config.php` match the database you imported.
- If there are no universities in the setup wizard, import `database/schema.sql` first.
- The schema's sample rows are not credentials and cannot be used to log in. Create every account through the one-time setup or **User accounts**.
- A student with academic or financial history cannot be hard-deleted; change the student's status to `withdrawn` to preserve history. Colleges with records cannot be hard-deleted; deactivate instead. Related/used exams, staff, and charges are protected from destructive deletes.
- Only local XAMPP operation is targeted. Enable HTTPS and set secure deployment-specific session/database configuration before any non-local use.

## Validation

PHP syntax checks were run with the XAMPP PHP 8.2 CLI. The SQL schema was imported into a separate local MariaDB test database and the setup wizard, sign-in, dashboard, attendance create/delete, report rendering, and printable receipt were smoke-tested. See deployment notes in the delivery summary for any environment-specific checks.
#   C o l l e g e - E R P - M a n a g e m e n t - S y s t e m  
 