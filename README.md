# HRMS

HR and payroll for many companies in one installation. A platform owner (super admin) creates companies; each company's admin and HR users manage their own employees, attendance, leave, borrow/advance and monthly payroll. Employees never log in: this is an admin-only system.

**User guides (Hinglish):** [docs/README.md](docs/README.md) explains every module for the people who use the system day to day.

## What it does

| Area                     | Features                                                                                                                                         |
| ------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| Companies                | Super admin creates, edits, activates and deactivates companies, and can sign in as any company user to help them                                |
| Users and roles          | Company Admin, HR Manager and Viewer roles per company, with editable permissions                                                                |
| Employees                | Profiles, departments, designations, documents, exit and a past-employees list (employees are never deleted)                                     |
| Shifts and holidays      | Work shifts by employee, by gender or as a company default; weekly and public holidays                                                           |
| Attendance               | Automatic or manual, monthly calendar and list views, day details, every edit kept in a history                                                  |
| Leave                    | Leave types, requests with approval, balances                                                                                                    |
| Short hours and overtime | Deduct, record only or decide manually; overtime by rate or fixed amount                                                                         |
| Borrow / advance         | Existing borrow at joining, new borrows, installments, recovery through payroll or directly, borrow given with salary                            |
| Salary                   | Salary structure, revisions with an effective date (older revisions stay as history), bonuses and one-off deductions                             |
| Payroll                  | Draft → Calculated → Admin Review → Adjusted → Finalized, with System / Adjustment / Final amounts for every line, lock and reopen with a reason |
| Salary slips             | PDF slip per employee per month                                                                                                                  |
| Final settlement         | Closing calculation for an employee who leaves                                                                                                   |
| Reports and dashboard    | Dashboard with filters and charts, attendance / payroll / borrow reports, audit log                                                              |

Each company only ever sees its own data. Every company-owned record carries the company it belongs to, and a request for another company's record answers "not found".

## Built with

Laravel 13, Inertia 3, Vue 3 (TypeScript), Tailwind CSS 4, MySQL. PDFs are made with dompdf, charts with Chart.js.

## Requirements

- PHP 8.4 or newer with the usual Laravel extensions (`mbstring`, `pdo_mysql`, `dom`, `gd`, `fileinfo`, `openssl`)
- MySQL 8 or MariaDB 10.4+
- Composer 2
- Node.js 22 or newer (only to build the frontend)

## Run it locally

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create a MySQL database named `hrms`, set the `DB_*` values in `.env`, then:

```bash
php artisan migrate --seed
npm install
composer run dev
```

`composer run dev` starts the web server, the queue worker and Vite together; the site opens at <http://localhost:8000>. On Windows with Laragon and XAMPP, `dev.bat` does the same and also starts the database server.

### Logins after seeding

| Who                     | Email                           | Password                           |
| ----------------------- | ------------------------------- | ---------------------------------- |
| Super admin             | `SUPER_ADMIN_EMAIL` from `.env` | `SUPER_ADMIN_PASSWORD` from `.env` |
| Acme company admin      | `admin@acme.test`               | `DEMO_PASSWORD` from `.env`        |
| Acme HR manager         | `hr@acme.test`                  | same                               |
| Acme viewer (read only) | `viewer@acme.test`              | same                               |
| Globex company admin    | `admin@globex.test`             | same                               |

The two demo companies come with employees, attendance, borrows and payrolls so every screen has something to show. Set `SEED_DEMO_DATA=false` before seeding to create only the super admin.

The default passwords are published in this repository. **Change the super admin password after the first login** on any site that other people can reach.

To add another super admin later:

```bash
php artisan hrms:create-super-admin
```

## Tests and checks

Tests run on MySQL, in a database named `hrms_testing` (create it once).

```bash
php artisan test --compact     # tests
vendor/bin/pint                # PHP formatting
vendor/bin/phpstan analyse     # static analysis
npm run check                  # frontend lint and formatting
npm run types:check            # TypeScript
```

## Deployment

Every push to `master` runs `.github/workflows/deploy.yml`, which builds the frontend and uploads the application to the hosting account over FTPS.

- The FTP password is read from the repository secret `FTP_PASSWORD`.
- The upload folder is `./hrms.fahad-jadiya.com/`. Set a repository variable named `FTP_SERVER_DIR` to use another folder.
- `vendor` is **not** uploaded, and the server's own `.env` is never touched.

### First-time setup on the server

1. Point the domain at the uploaded folder. The `.htaccess` in the project root forwards every request to `public/`, so the domain can point at the project folder itself.
2. Put `vendor` on the server: run `composer install --no-dev --optimize-autoloader` there, or upload a `vendor` folder built with the same PHP version. Repeat this whenever `composer.lock` changes.
3. Create `.env` on the server from `.env.example`. Set `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL`, an `APP_KEY` (generate one locally with `php artisan key:generate --show`) and the `DB_*` values.
4. Open `/deploy/run` in a browser, then `/deploy/seed` once.
5. Log in as the super admin and change the password.
6. Set `DEPLOY_ROUTES_ENABLED=false` in the server `.env` when you no longer need the deploy URLs.

### Deploy URLs

These run deployment tasks from the browser, for hosting without SSH. They need no login.

| URL                    | What it does                                                         |
| ---------------------- | -------------------------------------------------------------------- |
| `/deploy`              | Lists the URLs below                                                 |
| `/deploy/run`          | Folders, clear caches, storage link and pending migrations in one go |
| `/deploy/setup`        | Creates any missing storage and cache folders                        |
| `/deploy/migrate`      | Runs pending migrations only                                         |
| `/deploy/seed`         | Seeds the database, only while it has no users                       |
| `/deploy/storage-link` | Creates the public storage link                                      |
| `/deploy/cache`        | Caches config, routes and views                                      |
| `/deploy/clear`        | Clears those caches                                                  |

**Existing data is safe.** `/deploy/migrate` only runs migrations that have not run yet; nothing behind these URLs drops, resets or overwrites tables, and `/deploy/seed` refuses to run once any user exists. Missing storage and cache folders are also created automatically whenever the application starts.

### Background work

Two cron entries keep attendance and large jobs running on the server:

```cron
* * * * * php /path/to/project/artisan schedule:run >> /dev/null 2>&1
* * * * * php /path/to/project/artisan queue:work --stop-when-empty >> /dev/null 2>&1
```

The scheduler creates each day's attendance records for companies on automatic attendance. The queue is only needed for large companies: payroll for up to 250 employees is calculated straight away without it.
