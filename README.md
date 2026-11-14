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
6. Add teachers, create classes with their fees, and create batches inside each class. Add student profiles and enroll them from Batch Detail. Set the school name, academic year, and currency in School settings.

Alternatively, run the PHP development server:

```powershell
php -S 127.0.0.1:8000 -t public public/router.php
```

Then open http://127.0.0.1:8000. MySQL must still be running.

## Included features

- Administrator setup, sign-in, sign-out, and password changes.
- Dashboard with live enrollment, class capacity, teachers, fee balances, and a student-origin pie chart. The chart counts active student profiles once across Facebook, TikTok, Telegram, Phone, and Other, with counts, percentages, hover/focus details, and an empty state.
- Student profiles with name, phone, address, origin (Facebook, TikTok, Telegram, Phone, Other), account link/phone, and optional multiline notes/description (up to 5,000 characters); search and filter by class/status. Student IDs are generated internally and hidden from the interface and exports. Existing guardian and demographic records are retained.
- Classes with teacher assignment, default fee, and default batch capacity. Each class has multiple batches with dates, capacity, and Open/Ongoing/Completed status. A student can join multiple classes or multiple batches of a class.
- Batch enrollment records the received fee as fully paid and adds it to finance income automatically. The amount defaults to the class fee and can be adjusted; choose Cash, Bank transfer, or Mobile payment. Select FOC (free of charge) to waive the fee: the received amount becomes zero, no payment is created, and no finance income is added. Zero-fee enrollment is also FOC. Future enrollment/payment dates and duplicate enrollments are rejected. Fee changes do not rewrite earlier receipts.
- Student Detail includes all class/batch enrollments and enrollment receipts with paid/FOC information. Batch Detail combines student contact details, enrollment status controls, fees, paid/FOC status, and receipt links in one table. Multiple historical invoices are totaled per student enrollment, with each print link retained. Student Detail uses enrollment receipts without a separate payment-history table. Enrollment can be marked Active, Completed, or Withdrawn without deleting financial history.
- Mini Finance Book with manual income and expenses, automatic income from all student payments, date filters, and cash-basis profit (collected income minus expenses). Student fee income is read directly from payments and is counted once.
- A4 receipt/invoice printing with school branding, student/guardian details, payment history, totals, and balance due. Available from enrollment receipt rows.
- Dashboard financial charts with income, outcome (expenses), and profit line charts: daily totals for a selected month or monthly totals for a selected year. Includes period totals, zero-activity periods, negative profit, and an accessible data table. Enrollment payments and manual finance entries use their recorded dates; FOC adds no income.
- CSV exports for students and fee balances.
- School name, logo, favicon, academic year label, and currency settings. Branding appears in the sidebar, sign-in screen, and browser tab.
- Responsive layout, mobile navigation, keyboard-accessible dialogs, CSRF protection, escaped output, prepared SQL, and password hashing.
- App-bar light/dark theme toggle with a saved browser preference. The selected theme also applies to sign-in; printed invoices retain their paper-friendly colors.

No sample accounts or student records are inserted during setup. To add optional sample data, run `php bin/console db:seed`: this adds 10 active students and 5 demo classes with 2 ongoing batches per class. Rerunning it skips existing demo records. It does not create enrollments, invoices, or payments. Archiving preserves enrollment and financial history. Reactivate a student by editing their status.

To print an invoice, open Enrollment receipts and click Print A4 on its row. The invoice opens in a separate tab, preserving your list. Click Print invoice, select A4 portrait, and print or choose Save as PDF. For a clean copy, disable browser headers and footers in the print dialog. Long payment histories continue onto additional pages with repeated table headings. Printing is read-only and requires administrator sign-in; amounts reflect payments recorded at printing time.

## Operational notes

This is a single-administrator mini app. Academic year is a workspace label; changing it does not roll over classes or split records into separate years.

Invoices and recorded payments are retained as financial history. Enrollment receipts are read-only, with search by name, phone, invoice number, or class; searchable class/batch selectors; payment-status filters; and an inclusive enrollment-date range (legacy receipts without enrollment use due date). Receipt count, FOC enrollment count, and income received match the filters, as does CSV export. Invalid date ranges show an error and disable export. Existing historical unpaid and partial records keep their original balances; the new workflow does not backfill payments or reclassify old enrollments as FOC. The app does not include refunds, invoice cancellation, online payment processing, parent portals, timetable scheduling, or multiple staff roles. Currency is a display label; changing it does not convert existing amounts. Student exports contain all records, irrespective of directory filters. Enrollment receipt exports use the applied receipt filters; downloading from the dashboard exports all receipts.

In School settings, choose a logo and favicon, then click Save settings. Upload PNG, JPG, or WebP images up to 2 MB and 4096 × 4096 pixels. Logos keep their aspect ratio and are resized to at most 512 pixels. Favicons are saved as transparent 64 × 64 PNGs. Leave a file empty to keep the existing image, or select its removal checkbox to restore the default. Uploads are validated and re-encoded; original files are not retained.

Back up the `myra_school` database and the `public/uploads/branding/` directory regularly. Use phpMyAdmin Export or mysqldump for the database. Branding image files are local runtime data and are excluded from Git. For deployment, point the web server document root to `public/`, set `APP_DEBUG=false`, use HTTPS and a dedicated MySQL user, and complete initial administrator setup before opening access. Keep `.env` private. Fonts load from Google Fonts with system-font fallbacks; the app otherwise needs no external services.

## Verification

```powershell
php tests/integration.php
```

Tests create a randomly named `myra_test_*` database, launch a separate local PHP server on port 8019, exercise real HTTP requests against MySQL, then drop only that test database. The MySQL account must have permission to create/drop test databases. Your school database is not modified.

The suite checks authentication and CSRF, student profiles and hidden IDs, classes and batches, capacity, duplicate enrollment, multi-class enrollment, fee snapshots, automatic enrollment payments, FOC exclusion from income, and preserved historical payment records, finance totals and date filters, dashboard charts and exports, archiving, and settings.

Student, teacher, and class-filter selectors support typing to search, mouse selection, Arrow keys, Enter, Escape, and Tab. Options match all typed terms, including names and phone numbers. Long lists display up to 100 matches at once; typing searches the entire list. Native selects remain available when JavaScript is disabled. Fixed options such as status, origin, and payment method retain their ordinary dropdowns.

Optional selector interaction checks (requires Node.js; these dependencies are only for testing):

```powershell
npm.cmd install --prefix storage/cache/select-tests --no-save --package-lock=false jsdom
node tests/search-select.cjs
node tests/enrollment-form.cjs
node tests/reports.cjs
node tests/receipt-filters.cjs
node tests/student-origin.cjs
```

Run `php tests/migration.php` to verify upgrading legacy students, invoices, and payments into batches, including rollback preservation. It uses a separate randomly named disposable test database.

The batch migration preserves existing students and creates an "Existing students" batch for each class that already has students. Existing invoices are linked to the corresponding enrollment without adding new charges. Existing payments automatically appear in the finance book. Classes start with a zero default fee until an administrator sets it; existing invoice amounts remain unchanged. PHP syntax can also be checked with `php -l <file>`.

Attendance has been removed from the app, including its page, recording endpoint, exports, dashboard, and student history. Historical attendance rows remain in the database for retention; no attendance data is deleted by this change.

Exam results have been removed from navigation, forms, student detail, reports, recording endpoints, and exports. Historical result records remain in the database.

Financial report charts and CSV export cards are part of the dashboard. The standalone Reports & exports page and navigation item are removed. Old `/reports` links redirect to the dashboard financial chart while preserving month/year selections.
