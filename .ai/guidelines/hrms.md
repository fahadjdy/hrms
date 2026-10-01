# HRMS project rules

This is an admin-only, multitenant HRMS SaaS. There is no employee login, portal or self-service; employees are HR records managed by Company Admin / HR users. Do not build employee-facing features.

Activate the `hrms-architecture` skill before changing tenancy, attendance, salary, borrow, payroll or final-settlement code, or adding a tenant-owned model, controller or page.

## Tenancy (never break these)

- Hierarchy: Super Admin → Company (tenant) → Company Admin / HR users → Employees.
- Every tenant-owned model uses `App\Models\Concerns\BelongsToCompany`. It scopes all queries to the current company (and matches nothing when no company is set), fills `company_id` on create and forbids moving a record to another company.
- The tenant comes from the signed-in user only (`EnsureTenant` middleware → `App\Support\Tenancy\TenantContext`). Never read `company_id` from a route, query string or request body, and never add `company_id` to a model's fillable attributes.
- Validate ids from the request with `App\Support\Tenancy\TenantRule::exists()` / `::unique()`, never plain `exists:` / `unique:`.
- `User` is not tenant-scoped: check `$user->company_id` explicitly.
- Cross-tenant requests must answer 404. Add a case to `tests/Feature/Tenancy/TenantIsolationTest.php` for every new tenant route.
- Platform (super admin) code that must look across tenants calls `Model::withoutTenancy()` and reads counts and totals only. Queued jobs and commands set the tenant with `TenantContext::run($company, fn () => …)` (see `App\Jobs\Concerns\RunsForCompany`).

## Business logic

- Controllers only validate, authorize, call a service and respond. Calculations live in `app/Services`; nothing is calculated in Vue.
- Money: `DECIMAL(…, 2)` columns, rounded per line with `App\Support\Money`. Time worked: integer minutes.
- History is append-only: salary revisions, borrow transactions, attendance logs and audit logs are never edited or deleted. A salary change is a new `EmployeeSalaryRevision`.
- A finalized payroll is locked; changes go through `PayrollService::reopen()` with a reason.
- Call `App\Services\AuditLogger` for every change the spec lists as auditable.
- Employees are never deleted. Leaving the company is `EmployeeService::exit()`.

## Frontend

- Pages: `resources/js/pages/<area>/<Page>.vue`, TypeScript `<script setup>`, Wayfinder route helpers (never hardcoded URLs), shared components from `resources/js/components` (`PageHeader`, `SectionCard`, `DataTable`, `DataPagination`, `FormField`, `NativeSelect`, `StatusBadge`, `StatCard`, `ConfirmDialog`, charts under `components/charts`).
- Format money, dates and durations with `useFormat()`; meaning colors through the `positive` / `warning` / `negative` / `info` tokens and `lib/status.ts`.
- Every screen must work from phone to desktop: `DataTable` turns rows into cards below `md`, the attendance calendar has a list view.
- Borrow given is always shown separately from salary earnings and from borrow recovered.

## Environment

- PHP 8.3+ is required (this machine: `C:\laragon\bin\php\php-8.4.20`). Tests run against the MySQL database `hrms_testing`, not SQLite.
