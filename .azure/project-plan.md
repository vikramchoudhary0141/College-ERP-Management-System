# College ERP Management System — Project Plan

**Status: Approved by the user on 2026-10-24.**

## Goal

Build a locally runnable, database-backed College ERP for one university and
its multiple colleges. It will support the requested administrative and
academic roles, operational modules, secure access controls, and responsive
screens. The initial delivery target is XAMPP on Windows; production deployment
is out of scope for this iteration.

## Confirmed requirements

- Stack: PHP, MySQL, HTML, CSS, JavaScript, Bootstrap 5, and XAMPP.
- Organization: one university with multiple colleges.
- Roles: Super Admin, University Admin, College Admin, Teacher, Student, and
  Parent.
- Modules: admissions; students and staff; attendance; fees and receipts;
  examinations and results; library; hostel; transport; and reports.
- Implement functional create, read, update, and delete workflows where
  appropriate. Actions must persist to MySQL; no inert controls or mock
  business data.
- Local XAMPP delivery only.
- Create the initial Super Admin through a one-time setup page.
- Include complete source, SQL schema and sample data, and step-by-step
  installation instructions.

## Architecture

- Use PHP 8+ with a small, dependency-light MVC/front-controller structure,
  server-rendered HTML, and Bootstrap 5 for responsive layouts.
- Use MySQL with PDO, prepared statements, foreign keys, and explicit database
  transactions for multi-step operations such as fee receipts and admissions.
- Centralize authentication, authorization, validation, CSRF protection,
  output escaping, and college-scope enforcement; do not rely on hiding UI
  controls for access control.
- Model the hierarchy as university → colleges → users and operational
  records. University Admins can work across colleges in their university;
  College Admins, Teachers, Students, and Parents are restricted to assigned
  college/records. Super Admin manages the university and college directory.
- Link parent accounts only to their associated student(s). Enforce tenant
  scoping in server-side queries and writes for every module.
- Keep database configuration in a local, untracked configuration file and
  provide a safe example configuration. The one-time setup wizard creates the
  first Super Admin and locks itself once setup is complete.
- Avoid external services as runtime dependencies. Include Bootstrap assets
  locally or document a reliable local setup so the app remains usable without
  internet access.

## Roles and access

| Role | Intended access |
|---|---|
| Super Admin | University and college administration, global setup, and aggregate reports |
| University Admin | University-wide settings, users, academic operations, and reports across its colleges |
| College Admin | Full operational administration within assigned college |
| Teacher | Assigned classes/subjects, attendance, examinations, and results |
| Student | Own profile, attendance, fee status/receipts, timetable/exam results, and permitted library information |
| Parent | Read-only information for linked student(s), including attendance, fees, and results |

Permissions will be enforced per route/action and scoped by university,
college, and linked student, as applicable.

## Functional scope

1. **Setup and identity:** one-time Super Admin setup, login/logout, password
   hashing, session hardening, password changes, role/permission checks, and
   user activation/deactivation.
2. **Organization and admissions:** university/college records, admission
   applications and review statuses, student enrollment, and student records.
3. **People and academics:** staff/teacher records, academic years, classes,
   sections, subjects, and teacher assignments needed by attendance and exams.
4. **Attendance:** record and revise daily attendance for assigned classes;
   students and parents can see only applicable attendance.
5. **Fees:** configure fee types and student charges, record payments, produce
   database-backed printable receipts, and view balances/payment history.
6. **Examinations and results:** configure exams and subject marks, calculate
   totals/grades using documented rules, publish/unpublish results, and expose
   published results to the appropriate student/parent.
7. **Library:** catalog books, track copies, issue/return books, and show
   availability and overdue items.
8. **Hostel:** manage hostel/room records and student allocations, scoped to a
   college.
9. **Transport:** manage routes/stops/vehicles and student assignments, scoped
   to a college.
10. **Reports:** filterable database-backed summaries for admissions, student
    and staff rosters, attendance, fees, exam results, library circulation,
    hostel occupancy, and transport assignments. Provide print-friendly
    reports; exports only where implemented and validated.
11. **UI and documentation:** role-appropriate dashboards, responsive
    navigation, usable empty/error/success states, sample data, database
    install/import instructions, and local XAMPP setup/troubleshooting.

## Data and sample setup

Provide a versioned SQL schema with keys, indexes, constraints, and sample
records that demonstrate multiple colleges and core workflows. No hard-coded
demo password will be treated as secure credentials. The initial Super Admin
is created through the setup wizard; the wizard must validate input, hash the
password, and disable itself after successful setup.

## Security and correctness requirements

- PDO prepared statements for all user-influenced SQL.
- `password_hash` / `password_verify`, secure session settings, login
  throttling, CSRF tokens on state-changing forms, server-side validation, and
  context-appropriate output escaping.
- Explicit authorization and tenant-scope checks on every read/write, including
  object identifiers submitted by the browser.
- Database constraints and transactions for payments/receipts, enrollment,
  and other related multi-record changes.
- No secret credentials in source control; clear setup errors and actionable
  validation messages.

## Validation and acceptance

- Fresh XAMPP setup: import schema/sample data, run setup wizard, log in, and
  access the correct role dashboard.
- Exercise CRUD and lifecycle flows for each module against MySQL, including
  payment/receipt consistency, attendance, marks publication, library
  issue/return, and hostel/transport assignments.
- Verify role boundaries and cross-college isolation, including direct URL and
  manipulated-ID attempts for records in another college.
- Verify parent access is limited to linked student(s), and unpublished results
  are not exposed.
- Check responsive rendering and links/forms for each role; no nonfunctional
  action controls.
- Run available PHP syntax checks and focused integration/manual tests against
  the XAMPP MySQL database. Document any environment-specific setup required.

## Delivery

- PHP application source and static assets.
- SQL schema and non-credential sample records.
- One-time initial administrator setup flow.
- Installation and usage documentation for Windows/XAMPP.

## Build sequence

1. Establish application structure, environment configuration, database
   connection, schema, setup wizard, authentication, authorization, and
   tenant scoping.
2. Implement shared layouts, dashboards, organization, user/staff/student
   management, and admissions.
3. Implement academic setup, attendance, fees/receipts, and examinations/results.
4. Implement library, hostel, transport, and reporting.
5. Add/complete sample data and installation documentation; validate security,
   isolation, workflows, and responsive behavior end-to-end.

## Approval

The user approved this plan on 2026-10-24. Implementation may proceed according
to the scope and architecture above. Significant changes to the listed scope
or architecture require approval before implementation.
