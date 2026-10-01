---
name: hrms-architecture
description: 'System architecture of this multitenant HRMS: tenant isolation, the service layer, and how attendance, working hours, short hours, salary revisions, borrow / advance, payroll and final settlement fit together. Use when adding or changing a tenant-owned model, migration, service, controller, route, job or page; when touching payroll, attendance, borrow or salary calculations; when debugging a wrong pay amount; or when writing tests for any of these.'
---

# HRMS Architecture

An admin-only, multitenant HRMS. Single database, one row per company in `companies`, `company_id` on every tenant-owned table. Employees never log in.

## 1. Tenancy

| Piece              | Where                 | What it does                                                                                                                                                                      |
| ------------------ | --------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `TenantContext`    | `app/Support/Tenancy` | Scoped service holding the current `Company`; also `settings()`, `today()`, `now()` in the company timezone, and `run($company, fn)`                                              |
| `CompanyScope`     | `app/Support/Tenancy` | Global scope: `where company_id = current`; `where 1 = 0` when no company is set (fails closed)                                                                                   |
| `BelongsToCompany` | `app/Models/Concerns` | Adds the scope, fills `company_id` on create, throws if `company_id` changes, `withoutTenancy()` for platform code                                                                |
| `EnsureTenant`     | `app/Http/Middleware` | Sets the context from `auth()->user()->company`; logs out users of deactivated companies; runs **before** route model binding (see `bootstrap/app.php`), so a foreign id is a 404 |
| `TenantRule`       | `app/Support/Tenancy` | `exists` / `unique` validation limited to the current company                                                                                                                     |

Checklist for a new tenant-owned model:

1. Migration: `foreignId('company_id')->constrained()` plus an index that starts with `company_id` for the main query pattern.
2. Model: `use BelongsToCompany;` — do not list `company_id` in `#[Fillable]`.
3. Validation of related ids: `TenantRule::exists('table')`.
4. Routes go in `routes/tenant.php` under a `can:<permission>` group. Nested resources use `->scopeBindings()`.
5. Add the route to `TenantIsolationTest`.

Outside a request (jobs, commands, seeders, tests) nothing is visible until you set a tenant: `app(TenantContext::class)->run($company, fn () => …)`. `WorkingCalendarService` and `WorkingHoursCalculationService` cache per company; call `flush()` after changing holidays, weekly offs, shifts or settings (`TenantContext::flushSettings()`).

## 2. Roles and permissions

`App\Enums\Permission` lists every permission; each is a Gate, so routes use `can:employees.manage`. A user's `Role` (per company, `permissions` JSON) grants them. The super admin (`users.is_super_admin`, no company) only reaches `routes/admin.php` and may impersonate a Company Admin.

## 3. Services (`app/Services`)

| Service                                                                                    | Owns                                                                                                                                                                |
| ------------------------------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `WorkingCalendarService`                                                                   | weekly offs, holidays, working days                                                                                                                                 |
| `WorkingHoursCalculationService`                                                           | shift for an employee on a date (employee-specific → gender default → company default) and check-in/out → worked / short / overtime / late minutes                  |
| `AttendanceCalculationService`                                                             | resolving each day (stored record, else derived from calendar + attendance mode), monthly summary, marking a day (logged + audited), generation, leave ↔ attendance |
| `ShortHoursCalculationService`                                                             | short minutes → amount, by company mode (deduct / record only / manual) and admin adjustment                                                                        |
| `OvertimeCalculationService`                                                               | overtime entry amounts and what is payable in a period                                                                                                              |
| `SalaryRevisionService`                                                                    | append-only salary history; revision in effect on a date; segments within a period                                                                                  |
| `SalaryCalculationService`                                                                 | gross for a period (weighted across revisions), per-day and hourly rates                                                                                            |
| `BorrowCalculationService`                                                                 | borrow records, installment schedule, ledger transactions, recovery (never above outstanding), payout with salary, reversal                                         |
| `PayrollCalculationService`                                                                | **pure calculation** of one employee's pay → lines, buckets, notes                                                                                                  |
| `PayrollService`                                                                           | workflow: create → calculate → review → adjust → finalize → reopen                                                                                                  |
| `SalarySlipService`                                                                        | PDF slips (dompdf) for finalized payroll                                                                                                                            |
| `FinalSettlementService`                                                                   | settlement for a past employee                                                                                                                                      |
| `LeaveService`, `EmployeeService`, `CompanyProvisioner`, `DashboardService`, `AuditLogger` | as named                                                                                                                                                            |

## 4. Payroll calculation flow

`PayrollCalculationService::calculate($employee, $period)`:

1. Resolve every day of the period (`AttendanceCalculationService::resolveDays`) and summarize it.
2. Salary for the period from the revisions in effect; per-day rate = gross ÷ divisor, where the divisor follows the company's `salary_calculation_method` (working days / calendar days / fixed 30).
3. Build **lines**, each `{code, label, bucket, amount, note, meta}`. The `note` is the plain-language derivation ("2 absent day(s) x 1,000.00 per day").
4. Lines roll up into **buckets** (`App\Enums\PayrollBucket`): gross salary, overtime, bonus, other earnings, attendance deduction, unpaid leave, short hours, borrow recovery, other deductions, borrow given.
5. Each bucket has `system`, `adjustment`, `final`. Manual adjustments (`payroll_adjustments`) are signed deltas on a bucket and survive recalculation.
6. `net_salary = earnings − deductions`; `net_payable = net_salary + borrow_given`. A new borrow is an advance, never an earning.

Rules that are easy to get wrong:

- Attendance-based deductions are capped at the gross salary.
- Borrow recovery is capped at the pay left after all other lines, and by `borrow_max_deduction_percent` when set.
- In manual attendance mode an unmarked past working day counts as absent (and raises a warning on the item).
- Days before joining / after the last working day are "not employed" and deducted at the per-day rate.
- Overtime, bonuses and deductions are picked up when unpaid and dated on or before the period end, so a late entry is paid in the next payroll.

`PayrollService::finalize()` posts the effects in one transaction: pays out with-salary borrows, records recoveries in the borrow ledger, marks overtime / bonus / deduction entries as paid (`payroll_item_id`), creates salary slip records, locks the payroll. `reopen()` reverses exactly those postings (reversal transactions, entries unlinked, slips removed) and requires a reason.

The stored `payroll_items.breakdown` JSON is the full result of the calculation. Screens and PDFs read it, so a finalized payroll never changes when source data changes later.

## 5. Borrow / advance

`employee_borrows` (one row per borrow, `kind` existing|new, `disbursement_method` direct|with_salary) → `borrow_installments` (the schedule; pending rows are regenerated after every balance change) → `borrow_transactions` (append-only ledger: opening, disbursement, recovery, settlement, reversal).

Invariant: `opening_balance = recovered_amount + outstanding_amount`. Only `BorrowCalculationService` changes balances.

## 6. Attendance

`attendances` holds one row per employee per date: status, check-in/out, and `required / worked / short / overtime / late` **minutes**. A day without a row is derived, never assumed: weekly off, holiday, present (automatic mode) or unmarked (manual mode). The calendar, the summary, the dashboard and payroll all go through `resolveDays()` so they agree.

## 7. Queues

Heavy work has a job (`CalculatePayroll`, `GenerateSalarySlips`, `GenerateAttendance`) using `RunsForCompany`. Small companies run synchronously below the limits in `config/hrms.php`.

## 8. Testing

Tests use MySQL (`hrms_testing`). `Tests\Concerns\InteractsWithCompanies` provides `createCompany()`, `useCompany()`, `adminOf()`, `userWithRole()`, `configure()`, `createEmployee($attributes, $monthlySalary)`, `setSalary()`, `markAttendance()`. Freeze time with `travelTo()`; September 2026 with Sunday off has 26 working days, so a 26,000 salary is 1,000 per day.
