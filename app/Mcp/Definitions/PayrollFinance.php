<?php

// MCP tool definitions for payroll, salary, employee finance (borrows, overtime,
// short hours, deductions, bonuses) and final settlements. Each entry maps one
// named web route to one tool. Fields are read from the controller's validation
// rules / request reads.
//
// Payroll workflow: create_payroll -> calculate_payroll -> start_payroll_review
// -> add_payroll_adjustment (optional) -> finalize_payroll -> salary slips.

$page = ['type' => 'integer', 'description' => 'Page number (default 1).'];

$payrollParam = ['type' => 'integer', 'description' => 'Payroll id (from list_payrolls).'];
$itemParam = ['type' => 'integer', 'description' => 'Payroll item id (one employee line of the payroll, from get_payroll items[].id).'];
$employeeParam = ['type' => 'integer', 'description' => 'Employee id.'];
$pastEmployeeParam = ['type' => 'integer', 'description' => 'Employee id of a past (exited) employee (from list_final_settlements).'];

$adjustableBuckets = [
    'gross_salary', 'overtime_amount', 'bonus_amount', 'other_earnings_amount', 'attendance_deduction',
    'unpaid_leave_deduction', 'short_hours_deduction', 'borrow_recovery', 'other_deductions',
];

$overtimeFields = [
    'employee_id' => ['type' => 'integer', 'required' => true, 'description' => 'Employee id (from list_overtime employees[] or the employee list).'],
    'date' => ['type' => 'string', 'required' => true, 'description' => 'Overtime date, YYYY-MM-DD.'],
    'calculation_type' => ['type' => 'string', 'required' => true, 'enum' => ['hourly', 'fixed'], 'description' => 'hourly = hours x rate; fixed = a fixed amount.'],
    'hours' => ['type' => 'number', 'description' => 'Overtime hours, 0.25-24. Required when calculation_type is hourly (optional, informational for fixed).'],
    'rate' => ['type' => 'number', 'description' => 'Rate per hour in company currency. Required when calculation_type is hourly; ignored for fixed.'],
    'amount' => ['type' => 'number', 'description' => 'Fixed overtime amount in company currency (min 0.01). Required when calculation_type is fixed; for hourly the amount is computed.'],
    'reason' => ['type' => 'string', 'description' => 'Short reason, max 255 chars.'],
    'notes' => ['type' => 'string', 'description' => 'Notes, max 2000 chars.'],
    'status' => ['type' => 'string', 'required' => true, 'enum' => ['pending', 'approved', 'rejected'], 'description' => 'Only approved overtime is included in payroll. (paid is set by payroll finalization, not here.)'],
];

$deductionFields = [
    'employee_id' => ['type' => 'integer', 'required' => true, 'description' => 'Employee id (from list_deductions employees[] or the employee list).'],
    'date' => ['type' => 'string', 'required' => true, 'description' => 'Date, YYYY-MM-DD. Decides which payroll month it falls into.'],
    'title' => ['type' => 'string', 'required' => true, 'description' => 'Title, max 255 chars (e.g. "Uniform", "Damage").'],
    'amount' => ['type' => 'number', 'required' => true, 'description' => 'Amount in company currency, min 0.01.'],
    'reason' => ['type' => 'string', 'description' => 'Reason, max 255 chars.'],
];

$bonusFields = [
    'employee_id' => ['type' => 'integer', 'required' => true, 'description' => 'Employee id (from list_bonuses employees[] or the employee list).'],
    'date' => ['type' => 'string', 'required' => true, 'description' => 'Date, YYYY-MM-DD. Decides which payroll month it falls into.'],
    'type' => ['type' => 'string', 'required' => true, 'enum' => ['bonus', 'other_earning'], 'description' => 'bonus or other_earning (e.g. allowance, incentive).'],
    'title' => ['type' => 'string', 'required' => true, 'description' => 'Title, max 255 chars.'],
    'amount' => ['type' => 'number', 'required' => true, 'description' => 'Amount in company currency, min 0.01.'],
    'reason' => ['type' => 'string', 'description' => 'Reason, max 255 chars.'],
];

return [
    /* ---------------- Payroll runs ---------------- */
    [
        'name' => 'list_payrolls',
        'route' => 'payroll.index',
        'title' => 'List payroll runs',
        'description' => 'Monthly payroll runs of your company, newest month first, 12 per page. Returns payrolls (paginated) with id, label, period_year/month, period_start/end, status (draft, calculated, under_review, adjusted, finalized), is_locked, employee_count and totals (gross, earnings, deductions, borrow given/recovered, net payable), plus suggestedMonth (YYYY-MM) for the next run. Use the id with get_payroll. Requires permission payroll.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'page' => $page,
        ],
    ],
    [
        'name' => 'get_payroll_reports',
        'route' => 'payroll.reports',
        'title' => 'Payroll reports',
        'description' => 'Payroll summary report: the last 24 payroll runs with totals, and a department-wise breakdown (employees, gross, overtime, bonus, attendance deductions, short hours, borrow recovery, other deductions, borrow given, net payable) for the selected payroll. Returns payrolls[], selectedPayrollId, departments[]. Requires permission payroll.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'payroll_id' => ['type' => 'integer', 'description' => 'Payroll id to break down by department (one of the 24 latest, from payrolls[] or list_payrolls). Defaults to the latest payroll.'],
        ],
    ],
    [
        'name' => 'get_payroll',
        'route' => 'payroll.show',
        'title' => 'Get payroll run',
        'description' => 'One payroll run with its per-employee items (25 per page, sorted by name). Returns payroll (status, totals, notes, reopen_reason, finalized_by), items (paginated: id, employee, gross_salary, overtime, bonus, deductions, borrow_recovery, borrow_given, net_salary, net_payable, days, is_adjusted, warnings count), departments[] for filtering, and the workflow statuses. Workflow: create -> calculate -> review -> adjust -> finalize -> slips. Requires permission payroll.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [
            'payroll' => $payrollParam,
        ],
        'fields' => [
            'search' => ['type' => 'string', 'description' => 'Filter items by employee name or employee code.'],
            'department_id' => ['type' => 'integer', 'description' => 'Filter items by department id (from departments[] in this response).'],
            'status' => ['type' => 'string', 'enum' => ['adjusted', 'calculated'], 'description' => 'adjusted = items with manual adjustments; calculated = items without.'],
            'page' => $page,
        ],
    ],
    [
        'name' => 'get_payroll_item',
        'route' => 'payroll.items.show',
        'title' => 'Get payroll item (employee pay breakdown)',
        'description' => 'Full pay breakdown of one employee in a payroll: salary components, attendance summary, short hours, breakdown lines, buckets (system / adjustment / final per bucket), totals, warnings, manual adjustments (id, bucket, amount, reason, user) and adjustableBuckets. item.slip_id is set once finalized (use with get_salary_slip). Requires permission payroll.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [
            'payroll' => $payrollParam,
            'item' => $itemParam,
        ],
        'fields' => [],
    ],
    [
        'name' => 'create_payroll',
        'route' => 'payroll.store',
        'title' => 'Create payroll run',
        'description' => 'Create a draft payroll for a month (one payroll per month; fails if one exists). Step 1 of the workflow: create -> calculate_payroll -> start_payroll_review -> add_payroll_adjustment -> finalize_payroll -> salary slips. Redirects to the new payroll. Requires permission payroll.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'month' => ['type' => 'string', 'required' => true, 'description' => 'Payroll month, YYYY-MM (see suggestedMonth from list_payrolls).'],
            'notes' => ['type' => 'string', 'description' => 'Notes, max 2000 chars.'],
        ],
    ],
    [
        'name' => 'delete_payroll',
        'route' => 'payroll.destroy',
        'title' => 'Delete payroll run',
        'description' => 'Delete a payroll that is not finalized (a finalized payroll is locked; reopen it first with reopen_payroll). Requires permission payroll.manage.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'payroll' => $payrollParam,
        ],
        'fields' => [],
    ],
    [
        'name' => 'calculate_payroll',
        'route' => 'payroll.calculate',
        'title' => 'Calculate payroll',
        'description' => 'Calculate (or recalculate) every current employee\'s pay for the payroll from salary, attendance, leave, short hours, approved overtime, bonuses, deductions and borrow installments. Step 2 of create -> calculate -> review -> adjust -> finalize -> slips. Fails if the payroll is finalized (locked). For large companies the calculation is queued; re-read get_payroll after a moment. Requires permission payroll.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'payroll' => $payrollParam,
        ],
        'fields' => [],
    ],
    [
        'name' => 'start_payroll_review',
        'route' => 'payroll.review',
        'title' => 'Move payroll to admin review',
        'description' => 'Set a calculated payroll\'s status to under_review (step 3 of create -> calculate -> review -> adjust -> finalize -> slips). Fails if the payroll is not calculated yet or is finalized. Requires permission payroll.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'payroll' => $payrollParam,
        ],
        'fields' => [],
    ],
    [
        'name' => 'add_payroll_adjustment',
        'route' => 'payroll.items.adjustments.store',
        'title' => 'Add payroll adjustment',
        'description' => 'Add a manual signed adjustment to one bucket of an employee\'s payroll item (step 4: adjust, before finalize). The amount is a change to the bucket: +500 on a deduction bucket deducts 500 more, -500 deducts 500 less; a bucket cannot go negative, and borrow_recovery cannot exceed the employee\'s outstanding borrow. Adjusting moves the payroll to status adjusted. A new borrow (borrow_given) cannot be adjusted here - change the borrow itself. Fails on a finalized payroll. Requires permission payroll.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'payroll' => $payrollParam,
            'item' => $itemParam,
        ],
        'fields' => [
            'bucket' => ['type' => 'string', 'required' => true, 'enum' => $adjustableBuckets, 'description' => 'Bucket to adjust (see adjustableBuckets from get_payroll_item).'],
            'amount' => ['type' => 'number', 'required' => true, 'description' => 'Signed change in company currency, non-zero, between -999999999 and 999999999.'],
            'reason' => ['type' => 'string', 'required' => true, 'description' => 'Reason, 3-255 chars.'],
        ],
    ],
    [
        'name' => 'delete_payroll_adjustment',
        'route' => 'payroll.items.adjustments.destroy',
        'title' => 'Remove payroll adjustment',
        'description' => 'Remove a manual adjustment from a payroll item (not allowed on a finalized payroll). Requires permission payroll.manage.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'payroll' => $payrollParam,
            'item' => $itemParam,
            'adjustment' => ['type' => 'integer', 'description' => 'Adjustment id (from get_payroll_item adjustments[].id).'],
        ],
        'fields' => [],
    ],
    [
        'name' => 'finalize_payroll',
        'route' => 'payroll.finalize',
        'title' => 'Finalize payroll',
        'description' => 'Lock a calculated payroll and post its effects: borrows paid with salary are disbursed, borrow installments are recovered, included overtime/bonuses/deductions are marked paid, and salary slips are generated. Step 5 of create -> calculate -> review -> adjust -> finalize -> slips. After this the payroll is locked; changes need reopen_payroll with a reason. Requires permission payroll.finalize.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'payroll' => $payrollParam,
        ],
        'fields' => [],
    ],
    [
        'name' => 'reopen_payroll',
        'route' => 'payroll.reopen',
        'title' => 'Reopen finalized payroll',
        'description' => 'Reopen a finalized payroll so it can be changed: reverses its borrow postings, releases overtime/bonuses/deductions back to unpaid and removes its salary slips. Only a finalized payroll can be reopened; a reason is required and audited. Recalculate and finalize again afterwards. Requires permission payroll.finalize.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'payroll' => $payrollParam,
        ],
        'fields' => [
            'reason' => ['type' => 'string', 'required' => true, 'description' => 'Reason for reopening, 5-255 chars.'],
        ],
    ],

    /* ---------------- Salary ---------------- */
    [
        'name' => 'list_salary_structures',
        'route' => 'salary.index',
        'title' => 'List employee salary structures',
        'description' => 'Current employees with their salary effective today, 15 per page. Returns employees (paginated: employee brief, gross, effective_date, components[] name/type/amount, revisions_count), filters and departments[]. Use get_employee_salary for history. Requires permission payroll.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'search' => ['type' => 'string', 'description' => 'Employee name, employee code or email.'],
            'department_id' => ['type' => 'integer', 'description' => 'Department id (from departments[] in this response).'],
            'page' => $page,
        ],
    ],
    [
        'name' => 'get_employee_salary',
        'route' => 'employees.salary.show',
        'title' => 'Get employee salary and revision history',
        'description' => 'One employee\'s current salary revision and full revision history (newest first, each with effective_date, previous/new gross, components with code/name/type/amount, reason, notes, changed_by, is_current, is_upcoming). Requires permission payroll.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [
            'employee' => $employeeParam,
        ],
        'fields' => [],
    ],
    [
        'name' => 'create_salary_revision',
        'route' => 'employees.salary.store',
        'title' => 'Revise employee salary',
        'description' => 'Record a new salary revision for an employee, effective from a date. History is append-only: every salary change is a new revision with the full component list (send all components, not just the changed ones; call get_employee_salary for the current ones). Gross is the sum of the earning components; components at 0 are dropped and at least one earning must be above 0. Only one revision per employee per effective date. Requires permission payroll.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'employee' => $employeeParam,
        ],
        'fields' => [
            'effective_date' => ['type' => 'string', 'required' => true, 'description' => 'YYYY-MM-DD; cannot be before the employee\'s joining date.'],
            'reason' => ['type' => 'string', 'description' => 'Reason (e.g. "Annual increment"), max 255 chars.'],
            'notes' => ['type' => 'string', 'description' => 'Notes, max 2000 chars.'],
            'components' => [
                'type' => 'array',
                'required' => true,
                'description' => 'Salary components, 1-30 items.',
                'items' => [
                    'type' => 'object',
                    'properties' => [
                        'name' => ['type' => 'string', 'required' => true, 'description' => 'Component name, max 100 chars (e.g. "Basic", "HRA").'],
                        'type' => ['type' => 'string', 'required' => true, 'enum' => ['earning', 'deduction'], 'description' => 'earning or deduction.'],
                        'amount' => ['type' => 'number', 'required' => true, 'description' => 'Monthly amount in company currency, >= 0.'],
                    ],
                ],
            ],
        ],
    ],
    [
        'name' => 'list_salary_revisions',
        'route' => 'salary-revisions.index',
        'title' => 'List salary revisions',
        'description' => 'All salary revisions across employees, newest effective date first, 20 per page. Returns revisions (paginated: id, employee id/name/code, effective_date, previous_gross, new_gross, change, reason, changed_by, created_at) and filters. Requires permission payroll.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'search' => ['type' => 'string', 'description' => 'Employee name, code or email.'],
            'from' => ['type' => 'string', 'description' => 'Effective date from, YYYY-MM-DD (inclusive).'],
            'to' => ['type' => 'string', 'description' => 'Effective date to, YYYY-MM-DD (inclusive).'],
            'page' => $page,
        ],
    ],
    [
        'name' => 'list_salary_slips',
        'route' => 'salary-slips.index',
        'title' => 'List salary slips',
        'description' => 'Salary slips generated by finalized payrolls, newest first, 20 per page. Returns slips (paginated: id, slip_number, month, employee id/name/code, department, net_payable, generated_at), filters and payrolls[] (finalized payrolls id/label for the filter). Use the id with get_salary_slip. Requires permission payroll.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'search' => ['type' => 'string', 'description' => 'Employee name or employee code.'],
            'payroll_id' => ['type' => 'integer', 'description' => 'Only slips of this payroll (from payrolls[] in this response or list_payrolls).'],
            'page' => $page,
        ],
    ],
    [
        'name' => 'get_salary_slip',
        'route' => 'salary-slips.show',
        'title' => 'Get salary slip PDF',
        'description' => 'Returns the salary slip as a PDF document (application/pdf, file name <slip_number>.pdf), not JSON. Requires permission payroll.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [
            'slip' => ['type' => 'integer', 'description' => 'Salary slip id (from list_salary_slips or get_payroll_item item.slip_id).'],
        ],
        'fields' => [
            'download' => ['type' => 'boolean', 'description' => 'true = Content-Disposition attachment; default inline.'],
        ],
    ],

    /* ---------------- Borrows ---------------- */
    [
        'name' => 'get_borrow_form_options',
        'route' => 'borrows.create',
        'title' => 'Get new-borrow form options',
        'description' => 'Dropdown options for create_borrow: employees[] (current employees id/name), selectedEmployeeId, today, currentMonth and nextMonth (YYYY-MM). Requires permission finance.manage.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'employee_id' => ['type' => 'integer', 'description' => 'Preselect this employee (echoed back as selectedEmployeeId).'],
        ],
    ],
    [
        'name' => 'create_borrow',
        'route' => 'borrows.store',
        'title' => 'Record a borrow / advance',
        'description' => 'Record a borrow (loan / salary advance) for an employee with an installment schedule recovered through payroll. kind=new is issued by the company now; kind=existing is a balance the employee already owed when joining (use opening_balance for what is still outstanding). Disbursement direct = money handed over now (active immediately); with_salary = paid out with the payroll of disburse_period (pending_disbursement until that payroll is finalized; refused if that month\'s payroll is already finalized). An existing borrow is always recorded as direct. Borrow given is shown separately from salary earnings. Redirects to the new borrow. Requires permission finance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'employee_id' => ['type' => 'integer', 'required' => true, 'description' => 'Employee id (from get_borrow_form_options employees[]).'],
            'kind' => ['type' => 'string', 'required' => true, 'enum' => ['new', 'existing'], 'description' => 'new = issued by the company; existing = owed from before joining.'],
            'amount' => ['type' => 'number', 'required' => true, 'description' => 'Original borrow amount in company currency, min 0.01.'],
            'opening_balance' => ['type' => 'number', 'description' => 'Outstanding balance still to recover (<= amount). Only used for kind=existing (ignored for new); defaults to amount.'],
            'borrow_date' => ['type' => 'string', 'required' => true, 'description' => 'Borrow date, YYYY-MM-DD.'],
            'reason' => ['type' => 'string', 'description' => 'Reason, max 255 chars.'],
            'monthly_deduction' => ['type' => 'number', 'description' => 'Monthly recovery amount in company currency. Required unless installments_count is given.'],
            'installments_count' => ['type' => 'integer', 'description' => 'Number of monthly installments, 1-600. Required unless monthly_deduction is given.'],
            'deduction_start_month' => ['type' => 'string', 'required' => true, 'description' => 'First recovery month, YYYY-MM.'],
            'disbursement_method' => ['type' => 'string', 'required' => true, 'enum' => ['direct', 'with_salary'], 'description' => 'direct = paid outside payroll; with_salary = paid together with a payroll run.'],
            'disburse_period' => ['type' => 'string', 'description' => 'Salary month the borrow is paid with, YYYY-MM. Required when disbursement_method is with_salary.'],
            'source_reference' => ['type' => 'string', 'description' => 'External reference (e.g. voucher no.), max 255 chars.'],
            'notes' => ['type' => 'string', 'description' => 'Notes, max 2000 chars.'],
        ],
    ],
    [
        'name' => 'cancel_borrow',
        'route' => 'borrows.destroy',
        'title' => 'Cancel borrow',
        'description' => 'Cancel a borrow (status becomes cancelled; the ledger keeps a reversal - history is append-only). Not allowed once it has recoveries, or when it was paid out with a finalized payroll (reopen that payroll first). Check can_cancel from get_borrow. Requires permission finance.manage.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'borrow' => ['type' => 'integer', 'description' => 'Borrow id (from list_borrows).'],
        ],
        'fields' => [],
    ],
    [
        'name' => 'list_borrows',
        'route' => 'borrows.index',
        'title' => 'List borrows',
        'description' => 'Employee borrows / advances, newest first, 15 per page. Returns borrows (paginated: id, reference_no, employee, kind, amount, recovered, outstanding, monthly_deduction, borrow_date, disbursement_method, disburse_period, status), stats (total borrowed/recovered/outstanding, employees_with_borrow, active, fully_recovered, pending_disbursement, overdue), highestOutstanding[], upcomingDeductions[] and statuses. Requires permission finance.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'search' => ['type' => 'string', 'description' => 'Borrow reference no, or employee name / code / email.'],
            'status' => ['type' => 'string', 'enum' => ['pending_disbursement', 'active', 'recovered', 'cancelled'], 'description' => 'Borrow status.'],
            'kind' => ['type' => 'string', 'enum' => ['new', 'existing'], 'description' => 'Borrow kind.'],
            'page' => $page,
        ],
    ],
    [
        'name' => 'get_borrow',
        'route' => 'borrows.show',
        'title' => 'Get borrow',
        'description' => 'One borrow with employee, installment schedule (sequence, due_month, amount, paid_amount, status, via_payroll) and ledger transactions (opening, disbursement, recovery, reversal, settlement with balance_after). Includes can_cancel and can_recover flags. Requires permission finance.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [
            'borrow' => ['type' => 'integer', 'description' => 'Borrow id (from list_borrows).'],
        ],
        'fields' => [],
    ],
    [
        'name' => 'record_borrow_recovery',
        'route' => 'borrows.recoveries.store',
        'title' => 'Record manual borrow recovery',
        'description' => 'Record a manual (outside payroll) recovery against an active borrow, e.g. cash repaid. Amount cannot exceed the outstanding balance; date cannot be in the future. Recoveries are append-only ledger entries. Requires permission finance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'borrow' => ['type' => 'integer', 'description' => 'Borrow id (from list_borrows or list_borrow_recoveries activeBorrows[]).'],
        ],
        'fields' => [
            'amount' => ['type' => 'number', 'required' => true, 'description' => 'Amount recovered in company currency, 0.01 up to the outstanding balance.'],
            'date' => ['type' => 'string', 'required' => true, 'description' => 'Recovery date, YYYY-MM-DD, today or earlier.'],
            'notes' => ['type' => 'string', 'description' => 'Notes, max 255 chars (default "Manual recovery").'],
        ],
    ],
    [
        'name' => 'list_borrow_recoveries',
        'route' => 'borrow-recoveries.index',
        'title' => 'List borrow ledger transactions',
        'description' => 'Borrow ledger transactions across all borrows, newest first, 20 per page. Returns transactions (paginated: id, borrow_id, reference_no, employee, type, amount, balance_after, date, notes, user, via_payroll), stats (recovered_this_month, recovered_last_month), activeBorrows[] (id, label, outstanding) for record_borrow_recovery, and types. Requires permission finance.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'search' => ['type' => 'string', 'description' => 'Borrow reference no, or employee name / code / email.'],
            'type' => ['type' => 'string', 'enum' => ['opening', 'disbursement', 'recovery', 'reversal', 'settlement'], 'description' => 'Transaction type.'],
            'from' => ['type' => 'string', 'description' => 'Transaction date from, YYYY-MM-DD (inclusive).'],
            'to' => ['type' => 'string', 'description' => 'Transaction date to, YYYY-MM-DD (inclusive).'],
            'page' => $page,
        ],
    ],

    /* ---------------- Overtime ---------------- */
    [
        'name' => 'list_overtime',
        'route' => 'overtime.index',
        'title' => 'List overtime entries',
        'description' => 'Overtime entries for one month (default current month), newest first, 20 per page. Returns entries (paginated: id, employee, date, calculation_type, hours, rate, amount, reason, notes, status, is_locked), month, filters, totals (approved+paid amount and hours, pending count), statuses, employees[] (id/name options) and today. Requires permission finance.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'month' => ['type' => 'string', 'description' => 'YYYY-MM (default current month).'],
            'search' => ['type' => 'string', 'description' => 'Employee name, code or email, max 100 chars.'],
            'status' => ['type' => 'string', 'enum' => ['pending', 'approved', 'rejected', 'paid'], 'description' => 'Overtime status.'],
            'page' => $page,
        ],
    ],
    [
        'name' => 'create_overtime',
        'route' => 'overtime.store',
        'title' => 'Add overtime',
        'description' => 'Add an overtime entry for an employee. Hourly amount = hours x rate; fixed uses amount. Only approved overtime is paid by payroll. Requires permission finance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => $overtimeFields,
    ],
    [
        'name' => 'update_overtime',
        'route' => 'overtime.update',
        'title' => 'Update overtime',
        'description' => 'Replace an overtime entry: send every field (call list_overtime first to read current values). Overtime paid in a finalized payroll is locked and cannot be changed. Requires permission finance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'overtime' => ['type' => 'integer', 'description' => 'Overtime entry id (from list_overtime).'],
        ],
        'fields' => $overtimeFields,
    ],
    [
        'name' => 'delete_overtime',
        'route' => 'overtime.destroy',
        'title' => 'Delete overtime',
        'description' => 'Delete an overtime entry (not allowed once paid in a finalized payroll). Requires permission finance.manage.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'overtime' => ['type' => 'integer', 'description' => 'Overtime entry id (from list_overtime).'],
        ],
        'fields' => [],
    ],

    /* ---------------- Short hours ---------------- */
    [
        'name' => 'list_short_hours',
        'route' => 'short-hours.index',
        'title' => 'List short hours',
        'description' => 'Employees who worked short hours in a payroll period (default the current period), 15 per page, with the calculated short-hours deduction and any admin adjustment. Returns employees (paginated: employee brief + short_hours details), period, month, filters and the company rule (mode deduct / record_only / manual, rate type, fixed rate, tolerance minutes). Requires permission finance.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'month' => ['type' => 'string', 'description' => 'Payroll month, YYYY-MM (default the period containing today).'],
            'search' => ['type' => 'string', 'description' => 'Employee name, code or email, max 100 chars.'],
            'page' => $page,
        ],
    ],
    [
        'name' => 'set_short_hours_adjustment',
        'route' => 'short-hours.update',
        'title' => 'Set short-hours deduction',
        'description' => 'Set the admin-decided short-hours deduction amount for an employee and payroll month, overriding the calculated one (used by the next payroll calculation). Requires permission finance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'employee' => ['type' => 'integer', 'description' => 'Employee id (from list_short_hours).'],
        ],
        'fields' => [
            'month' => ['type' => 'string', 'required' => true, 'description' => 'Payroll month, YYYY-MM.'],
            'amount' => ['type' => 'number', 'required' => true, 'description' => 'Deduction amount in company currency, >= 0 (0 = waive).'],
            'reason' => ['type' => 'string', 'required' => true, 'description' => 'Reason, 3-255 chars.'],
        ],
    ],
    [
        'name' => 'reset_short_hours_adjustment',
        'route' => 'short-hours.destroy',
        'title' => 'Remove short-hours adjustment',
        'description' => 'Remove the admin adjustment for an employee and payroll month so the calculated short-hours deduction applies again. Requires permission finance.manage.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'employee' => ['type' => 'integer', 'description' => 'Employee id (from list_short_hours).'],
        ],
        'fields' => [
            'month' => ['type' => 'string', 'required' => true, 'description' => 'Payroll month, YYYY-MM.'],
        ],
    ],

    /* ---------------- Deductions ---------------- */
    [
        'name' => 'list_deductions',
        'route' => 'deductions.index',
        'title' => 'List salary deductions',
        'description' => 'One-off salary deductions for one month (default current month), newest first, 20 per page. Returns entries (paginated: id, employee, date, title, amount, reason, is_locked), month, filters, total, employees[] (id/name options) and today. Requires permission finance.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'month' => ['type' => 'string', 'description' => 'YYYY-MM (default current month).'],
            'search' => ['type' => 'string', 'description' => 'Employee name, code or email, max 100 chars.'],
            'page' => $page,
        ],
    ],
    [
        'name' => 'create_deduction',
        'route' => 'deductions.store',
        'title' => 'Add salary deduction',
        'description' => 'Add a one-off deduction for an employee; it is deducted by the payroll of the month its date falls in. Requires permission finance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => $deductionFields,
    ],
    [
        'name' => 'update_deduction',
        'route' => 'deductions.update',
        'title' => 'Update salary deduction',
        'description' => 'Replace a deduction: send every field (call list_deductions first to read current values). A deduction applied in a finalized payroll is locked. Requires permission finance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'deduction' => ['type' => 'integer', 'description' => 'Deduction id (from list_deductions).'],
        ],
        'fields' => $deductionFields,
    ],
    [
        'name' => 'delete_deduction',
        'route' => 'deductions.destroy',
        'title' => 'Delete salary deduction',
        'description' => 'Delete a deduction (not allowed once applied in a finalized payroll). Requires permission finance.manage.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'deduction' => ['type' => 'integer', 'description' => 'Deduction id (from list_deductions).'],
        ],
        'fields' => [],
    ],

    /* ---------------- Bonuses ---------------- */
    [
        'name' => 'list_bonuses',
        'route' => 'bonuses.index',
        'title' => 'List bonuses and other earnings',
        'description' => 'Bonuses and other earnings for one month (default current month), newest first, 20 per page. Returns entries (paginated: id, employee, date, type, title, amount, reason, is_locked), month, filters, total, employees[] (id/name options) and today. Requires permission finance.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'month' => ['type' => 'string', 'description' => 'YYYY-MM (default current month).'],
            'search' => ['type' => 'string', 'description' => 'Employee name, code or email, max 100 chars.'],
            'type' => ['type' => 'string', 'enum' => ['bonus', 'other_earning'], 'description' => 'Entry type.'],
            'page' => $page,
        ],
    ],
    [
        'name' => 'create_bonus',
        'route' => 'bonuses.store',
        'title' => 'Add bonus / other earning',
        'description' => 'Add a bonus or other earning for an employee; it is paid by the payroll of the month its date falls in. Requires permission finance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => $bonusFields,
    ],
    [
        'name' => 'update_bonus',
        'route' => 'bonuses.update',
        'title' => 'Update bonus / other earning',
        'description' => 'Replace a bonus or other earning: send every field (call list_bonuses first to read current values). An entry paid in a finalized payroll is locked. Requires permission finance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'bonus' => ['type' => 'integer', 'description' => 'Bonus entry id (from list_bonuses).'],
        ],
        'fields' => $bonusFields,
    ],
    [
        'name' => 'delete_bonus',
        'route' => 'bonuses.destroy',
        'title' => 'Delete bonus / other earning',
        'description' => 'Delete a bonus or other earning (not allowed once paid in a finalized payroll). Requires permission finance.manage.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'bonus' => ['type' => 'integer', 'description' => 'Bonus entry id (from list_bonuses).'],
        ],
        'fields' => [],
    ],

    /* ---------------- Final settlements ---------------- */
    [
        'name' => 'list_final_settlements',
        'route' => 'final-settlements.index',
        'title' => 'List final settlements',
        'description' => 'Past (exited) employees with their final settlement status, latest last working date first, 15 per page. Returns employees (paginated: employee brief, last_working_date, exit_type, outstanding_borrow, settlement {status, net_amount} or null) and filters. Settlement flow: get/prepare (draft) -> update (adjust) -> finalize -> mark paid. Requires permission settlements.manage.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'search' => ['type' => 'string', 'description' => 'Employee name, code or email.'],
            'status' => ['type' => 'string', 'enum' => ['not_started', 'draft', 'finalized', 'paid'], 'description' => 'not_started = no settlement yet; otherwise the settlement status.'],
            'page' => $page,
        ],
    ],
    [
        'name' => 'get_final_settlement',
        'route' => 'final-settlements.show',
        'title' => 'Get final settlement',
        'description' => 'Final settlement of a past employee. Viewing a not-yet-finalized settlement creates or refreshes the draft from current data. Net = last salary + overtime + bonus + other earnings - unpaid leave - short hours - outstanding borrow - other deductions +/- admin adjustment. Returns employee (with exit details), settlement (amounts, adjustment, net_amount, status, is_locked, finalized_at, paid_at, salary_already_paid), lines[], attendance, salary and warnings[]. Requires permission settlements.manage.',
        'read_only' => true,
        'destructive' => false,
        'params' => [
            'employee' => $pastEmployeeParam,
        ],
        'fields' => [],
    ],
    [
        'name' => 'recalculate_final_settlement',
        'route' => 'final-settlements.store',
        'title' => 'Prepare / recalculate final settlement',
        'description' => 'Create or recalculate the draft final settlement of a past employee from current salary, attendance, overtime, bonuses, deductions and outstanding borrows. Only for exited employees; a finalized settlement is not recalculated. Requires permission settlements.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'employee' => $pastEmployeeParam,
        ],
        'fields' => [],
    ],
    [
        'name' => 'update_final_settlement',
        'route' => 'final-settlements.update',
        'title' => 'Adjust final settlement',
        'description' => 'Set the admin adjustment and notes on a draft final settlement (the settlement must exist - call get_final_settlement or recalculate_final_settlement first; not allowed once finalized). Send all fields. Requires permission settlements.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'employee' => $pastEmployeeParam,
        ],
        'fields' => [
            'adjustment_amount' => ['type' => 'number', 'required' => true, 'description' => 'Signed adjustment to the net amount in company currency (+ pays more, - pays less; 0 for none).'],
            'adjustment_reason' => ['type' => 'string', 'description' => 'Reason, max 255 chars. Required unless adjustment_amount is 0.'],
            'notes' => ['type' => 'string', 'description' => 'Notes, max 2000 chars.'],
        ],
    ],
    [
        'name' => 'finalize_final_settlement',
        'route' => 'final-settlements.finalize',
        'title' => 'Finalize final settlement',
        'description' => 'Lock the draft final settlement and recover the employee\'s outstanding borrows through the borrow ledger (settlement transactions). Irreversible. Next step: mark_final_settlement_paid. Requires permission settlements.manage.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'employee' => $pastEmployeeParam,
        ],
        'fields' => [],
    ],
    [
        'name' => 'mark_final_settlement_paid',
        'route' => 'final-settlements.paid',
        'title' => 'Mark final settlement paid',
        'description' => 'Mark a finalized final settlement as paid (finalize it first). Requires permission settlements.manage.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'employee' => $pastEmployeeParam,
        ],
        'fields' => [],
    ],
];
