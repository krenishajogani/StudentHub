# StudentHub Portal

StudentHub is an integrated PHP/MySQL student portal for local development with XAMPP. The root directory is the deployable application; the `Practical-*` directories contain earlier practical snapshots and are blocked from the integrated site.

## Requirements

- XAMPP for Windows with Apache, MySQL/MariaDB, PHP 8.0 or newer, `mysqli`, `PDO`, and `pdo_mysql` enabled.
- A modern browser with JavaScript enabled.

## Install with XAMPP

1. Place this repository at `C:\xampp\htdocs\Student Hub Portal` (or update the virtual-host paths below).
2. Start **Apache** and **MySQL** in the XAMPP Control Panel.
3. Open phpMyAdmin at `http://localhost/phpmyadmin/`, select **Import**, and import `database_setup.sql`.
4. In the same database, import `events_migration.sql` and then `practical14_migration.sql`. On a fresh install, run both once, in that order; they add event poster storage, event registrations, and admin audit logging. The Practical 14 migration also adds a unique constraint preventing duplicate registrations.
5. Open `http://localhost/Student%20Hub%20Portal/` and register a student account. To make an account an administrator, run this in phpMyAdmin after registration, replacing the email:

   ```sql
   UPDATE studenthub.students SET role = 'admin' WHERE email = 'admin@example.com';
   ```

   Verify exactly one row was changed. Public registration always creates a student account.

The default local database settings are `localhost`, database `studenthub`, user `root`, and an empty password. For a non-default setup, set the `STUDENTHUB_DB_HOST`, `STUDENTHUB_DB_NAME`, `STUDENTHUB_DB_USER`, and `STUDENTHUB_DB_PASSWORD` environment variables for Apache before starting it. Do not commit credentials.

## Apache virtual host

1. Open `C:\xampp\apache\conf\extra\httpd-vhosts.conf` and **append** the contents of `apache-vhost.conf.example`. Do not overwrite the existing file, because it may contain other virtual hosts.
2. In `C:\xampp\apache\conf\httpd.conf`, make sure `LoadModule rewrite_module modules/mod_rewrite.so` and `Include conf/extra/httpd-vhosts.conf` are enabled (not commented out).
3. Add this line to `C:\Windows\System32\drivers\etc\hosts` using an elevated text editor:

   ```text
   127.0.0.1 studenthub.local
   ```

4. Restart Apache from the XAMPP Control Panel and browse to `http://studenthub.local/`.

The document root is quoted because its Windows path contains a space. If you install the project in another folder, update the document-root and directory paths in the virtual-host block. `.htaccess` disables directory indexes, prevents direct downloads of SQL/CSV and internal PHP helper files, blocks legacy practical copies, and routes the old dashboard link to the authenticated PHP dashboard. Keep `AllowOverride All` enabled for this directory.

## Integrated functionality

- Student registration and password-hash-based login; administrator accounts are assigned manually.
- Session-protected student dashboard and role-checked administrator dashboard/CRUD.
- Prepared-statement student management and event CRUD, with event CRUD AJAX and CSRF validation.
- Student event registration with CSRF validation and duplicate-registration protection.
- Contact form submission to the local CSV file and notice loading/filtering with Fetch.
- Responsive layouts and a browser-persisted light/dark theme.

## Practical 15 verification checklist

Run these checks against the local virtual host after setup:

1. **Navigation:** open the landing page; follow Register/Login; log in as a student and an administrator; confirm the dashboard and admin tools route according to role; use the legacy `dashboard.html` URL and confirm it reaches the authenticated dashboard.
2. **Authentication:** open `dashboard.php`, `admin_dashboard.php`, `students.php`, and `events.php` in a private browser session. Confirm the student pages redirect to login and admin pages reject students.
3. **Database:** register a student; verify the row in `students` has a password hash and `role = 'student'`; as admin, add/edit/delete a student and create/update/delete an event; verify changes in phpMyAdmin.
4. **Event registration:** as a student, register for an event; refresh the dashboard and confirm the registered state; submit the same registration again and confirm it is not duplicated.
5. **AJAX:** as admin, create/edit/delete an event without reloading the page; refresh the list; verify the JSON event-list request in the browser Network panel.
6. **Forms/security:** try invalid registration details, an invalid login, a stale/missing event CSRF token, and an oversized or non-image event poster. Confirm invalid requests fail safely. Confirm `/database_setup.sql`, `/events_migration.sql`, `/practical14_migration.sql`, `/contacts.csv`, `/db_mysqli.php`, and `/insert_student.php` are not downloadable.
7. **Responsiveness:** inspect login, dashboard, student CRUD, and event CRUD at desktop and narrow mobile widths; verify there is no page-wide horizontal overflow.
8. **Notices/contact:** check notice search, category, sorting, pagination, and fetch failure behavior; submit valid and invalid contact messages.

The XAMPP Apache/MySQL services and browser checks are machine-specific and must be run on the target workstation after following the install steps. PHP syntax can be checked from PowerShell with:

```powershell
Get-ChildItem -Filter *.php | ForEach-Object { & C:\xampp\php\php.exe -l $_.FullName }
```

## GitHub submission

From a clean, reviewed worktree, stage the application and documentation, inspect `git diff --cached`, commit the Practical 15 integration, and push the intended branch to the configured GitHub remote. Do not include local database credentials, exports, or personal contact submissions.

Before pushing, confirm `contacts.csv` and any `.env` file are not staged. The repository `.gitignore` excludes those local files and the older `Practical-*` snapshots; review the staged file list before creating the commit.
