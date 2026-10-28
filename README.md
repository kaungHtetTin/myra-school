# Myra School

A small-school student management app built with PHP 8.2+ and MySQL (also compatible with XAMPP MariaDB). PHP GD is required for logo and favicon uploads and is included in XAMPP. No Composer or Node build step is required.

## Run with XAMPP

1. Start Apache and MySQL from the XAMPP control panel.
2. Configure the database connection in `.env`. This installation uses `DB_HOST=127.0.0.1`, `DB_PORT=3306`, `DB_DATABASE=myra_school`, `DB_USERNAME=root`, and an empty local password. Use your own credentials if different.
3. From this project directory, run:

```powershell
php bin/console migrate
```

The migration command creates the configured database if needed and creates the tables. It is safe to rerun; applied migrations are skipped. If PHP is not in PATH, use `D:\xampp\php\php.exe` instead of `php`.

4. Open **http://localhost/myra-school/public/**.
5. Create your administrator account on the first-run screen. There is no preset password. Setup closes after the first account is created.
6. Add teachers, create classes, and then enroll students. Set the school name, academic year, and currency in School settings.

Alternatively, run the PHP development server:

```powershell
php -S 127.0.0.1:8000 -t public public/router.php
```

Then open http://127.0.0.1:8000. MySQL must still be running.

## Included features

- Administrator setup, sign-in, sign-out, and password changes.
- Dashboard with live enrollment, class capacity, today's recorded attendance, and fee balances.
- Student and guardian records; edit, search, filter by class/status, and archive/reactivate.
- Teachers and classes with teacher assignment and capacity checks.
- Daily attendance: Present, Absent, Late, Excused; mark all present; correct saved registers.
- Individual invoices, partial/full payments, payment methods, receipt numbers, and payment history. Overpayments are rejected.
- A4 invoice printing with school branding, student/guardian details, payment history, totals, and balance due. Available from invoice and recent-payment rows.
- Exam results by student, exam, and subject; entering the same combination corrects its score.
- CSV exports for students, all recorded attendance, fee balances, and exam results.
- School name, logo, favicon, academic year label, and currency settings. Branding appears in the sidebar, sign-in screen, and browser tab.
- Responsive layout, mobile navigation, keyboard-accessible dialogs, CSRF protection, escaped output, prepared SQL, and password hashing.
- App-bar light/dark theme toggle with a saved browser preference. The selected theme also applies to sign-in; printed invoices retain their paper-friendly colors.

No sample accounts or student records are inserted. Archiving preserves attendance, results, and finances. Reactivate a student by editing their status.

To print an invoice, open Fees & payments and click Print A4 on its row (or on a related recent payment). The invoice opens in a separate tab, preserving your list. Click Print invoice, select A4 portrait, and print or choose Save as PDF. For a clean copy, disable browser headers and footers in the print dialog. Long payment histories continue onto additional pages with repeated table headings. Printing is read-only and requires administrator sign-in; amounts reflect payments recorded at printing time.

## Operational notes

This is a single-administrator mini app. Academic year is a workspace label; changing it does not roll over classes or split records into separate years. Attendance percentages use marked, currently active students and count Present/Late as attending. Unmarked students are shown in the coverage count, not counted as absent. Historical attendance uses the student's current class assignment.

Invoices and recorded payments are retained as financial history. The app does not include refunds, invoice cancellation, online payment processing, parent portals, timetable scheduling, or multiple staff roles. Currency is a display label; changing it does not convert existing amounts. CSV exports contain all records, irrespective of the directory filters.

In School settings, choose a logo and favicon, then click Save settings. Upload PNG, JPG, or WebP images up to 2 MB and 4096 × 4096 pixels. Logos keep their aspect ratio and are resized to at most 512 pixels. Favicons are saved as transparent 64 × 64 PNGs. Leave a file empty to keep the existing image, or select its removal checkbox to restore the default. Uploads are validated and re-encoded; original files are not retained.

Back up the `myra_school` database and the `public/uploads/branding/` directory regularly. Use phpMyAdmin Export or mysqldump for the database. Branding image files are local runtime data and are excluded from Git. For deployment, point the web server document root to `public/`, set `APP_DEBUG=false`, use HTTPS and a dedicated MySQL user, and complete initial administrator setup before opening access. Keep `.env` private. Fonts load from Google Fonts with system-font fallbacks; the app otherwise needs no external services.

## Verification

```powershell
php tests/integration.php
```

Tests create a randomly named `myra_test_*` database, launch a separate local PHP server on port 8019, exercise real HTTP requests against MySQL, then drop only that test database. The MySQL account must have permission to create/drop test databases. Your school database is not modified.

The suite checks account setup, authentication, CSRF, student/teacher/class persistence, capacity, duplicate IDs, attendance updates, payments and overpayment prevention, result corrections, reports, archiving, and settings. PHP syntax can also be checked with `php -l <file>`.
