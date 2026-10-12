<?php

// MCP tool definitions for attendance, work shifts, holidays and leave.
// Each entry maps one named web route to one tool.
// Fields are read from the controller's validation rules / request reads.

$attendanceStatuses = ['present', 'absent', 'half_day', 'paid_leave', 'unpaid_leave', 'holiday', 'weekly_off', 'wfh', 'late', 'short_hours', 'other'];

$workShiftFields = [
    'name' => ['type' => 'string', 'required' => true, 'description' => 'Shift name, max 255, unique within the company.'],
    'start_time' => ['type' => 'string', 'required' => true, 'description' => 'Shift start, HH:MM 24h.'],
    'end_time' => ['type' => 'string', 'required' => true, 'description' => 'Shift end, HH:MM 24h; must differ from start_time (an end before the start means an overnight shift).'],
    'break_start' => ['type' => 'string', 'description' => 'Fixed break start, HH:MM 24h. Required together with break_end; send both or neither. The break must fall inside the shift.'],
    'break_end' => ['type' => 'string', 'description' => 'Fixed break end, HH:MM 24h. Required together with break_start; must differ from break_start.'],
    'required_minutes' => ['type' => 'integer', 'required' => true, 'description' => 'Required working minutes per day, 30-1440. Cannot exceed the shift span minus the break. Without fixed break times, the rest of the span counts as the break.'],
    'is_active' => ['type' => 'boolean', 'description' => 'Whether the shift is active (inactive shifts stay on record but are not offered for new assignments).'],
];

$holidayFields = [
    'name' => ['type' => 'string', 'required' => true, 'description' => 'Holiday name, max 255.'],
    'date' => ['type' => 'string', 'required' => true, 'description' => 'Holiday date, YYYY-MM-DD. Only one holiday per date in the company.'],
    'type' => ['type' => 'string', 'required' => true, 'enum' => ['public', 'company', 'optional', 'other'], 'description' => 'public = Public Holiday, company = Company Holiday, optional = Optional Holiday, other = Other.'],
    'description' => ['type' => 'string', 'description' => 'Optional description, max 255.'],
];

$leaveFields = [
    'leave_type_id' => ['type' => 'integer', 'required' => true, 'description' => 'Leave type id (from list_leave_types).'],
    'start_date' => ['type' => 'string', 'required' => true, 'description' => 'First day of leave, YYYY-MM-DD.'],
    'end_date' => ['type' => 'string', 'required' => true, 'description' => 'Last day of leave, YYYY-MM-DD, on or after start_date. Ignored (set to start_date) when is_half_day is true.'],
    'is_half_day' => ['type' => 'boolean', 'description' => 'Half-day leave (counts 0.5 day, single date). Default false; otherwise days = working days in the range (weekly offs and holidays excluded).'],
    'reason' => ['type' => 'string', 'description' => 'Reason, max 255.'],
    'notes' => ['type' => 'string', 'description' => 'Internal notes, max 2000.'],
];

$leaveTypeFields = [
    'name' => ['type' => 'string', 'required' => true, 'description' => 'Leave type name, max 255 (e.g. Casual Leave).'],
    'code' => ['type' => 'string', 'required' => true, 'description' => 'Short alphanumeric code, max 20, unique within the company (e.g. CL).'],
    'is_paid' => ['type' => 'boolean', 'required' => true, 'description' => 'Paid leave (true) or unpaid leave (false); drives attendance status and payroll.'],
    'annual_allowance' => ['type' => 'number', 'required' => true, 'description' => 'Default days allowed per year, 0-366 (decimals allowed).'],
    'is_active' => ['type' => 'boolean', 'description' => 'Whether the type is active (only active types are offered for new leave and balances).'],
];

return [
    // ---------------------------------------------------------------- Attendance
    [
        'name' => 'list_daily_attendance',
        'route' => 'attendance.index',
        'title' => 'Daily attendance',
        'description' => 'Company-wide attendance for one date (default today), for current employees who had joined by that date, paginated 25/page. Viewing a date stores days off (and present days in automatic mode) for it. Returns date, today, isFuture, holidayName, isWeeklyOff, mode (attendance mode), kpis (total, present, absent, on_leave, wfh, late, short_hours, overtime, unmarked), employees[] (id, name, code, department, designation, status, day = resolved status/check-in/out/worked minutes for the date), filters, departments[] and statuses[] options. Requires permission attendance.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'date' => ['type' => 'string', 'description' => 'Date to show, YYYY-MM-DD (default today in the company timezone).'],
            'search' => ['type' => 'string', 'description' => 'Matches employee first name, last name, employee code or email (max 100).'],
            'department_id' => ['type' => 'integer', 'description' => 'Department id (from the departments[] in this result or list_departments).'],
            'status' => ['type' => 'string', 'enum' => array_merge($attendanceStatuses, ['unmarked']), 'description' => 'Only employees whose stored attendance on the date has this status; "unmarked" = no stored record for the date.'],
            'page' => ['type' => 'integer', 'description' => 'Page number (default 1).'],
        ],
    ],
    [
        'name' => 'list_attendance_calendar_employees',
        'route' => 'attendance.calendar',
        'title' => 'Attendance calendar: pick employee',
        'description' => 'Current employees to open an attendance calendar for, paginated 24/page. Returns employees[] (id, name, code, department, designation, status), filters, departments[] and month (current YYYY-MM). Use an employee id with get_employee_attendance. Requires permission attendance.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'search' => ['type' => 'string', 'description' => 'Matches employee first name, last name, employee code or email.'],
            'department_id' => ['type' => 'integer', 'description' => 'Department id (from list_departments).'],
            'page' => ['type' => 'integer', 'description' => 'Page number (default 1).'],
        ],
    ],
    [
        'name' => 'get_employee_attendance',
        'route' => 'employees.attendance.show',
        'title' => 'Employee attendance month',
        'description' => 'One employee\'s attendance calendar for a month. Returns employee (brief + joining_date, last_working_date), month, monthLabel, previousMonth, nextMonth, today, days[] (per-date resolved status, check-in/out, worked/overtime/short minutes, notes), summary (counts, working days, attendance_rate), logs (change history per date: user, old, new, reason), shift (name, start/end time, required_minutes, source) and statuses[] options. Requires permission attendance.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [
            'employee' => ['type' => 'integer', 'description' => 'Employee id (from list_employees or list_attendance_calendar_employees).'],
        ],
        'fields' => [
            'month' => ['type' => 'string', 'description' => 'Month to show, YYYY-MM (default current month).'],
        ],
    ],
    [
        'name' => 'update_employee_attendance',
        'route' => 'employees.attendance.update',
        'title' => 'Mark or correct attendance day',
        'description' => 'Create or correct one employee\'s attendance for one date; overtime/short/late are recalculated from the shift and every change is logged and audited. Not allowed for a future date or a date outside the employee\'s employment (before joining or after last working day). Requires permission attendance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'employee' => ['type' => 'integer', 'description' => 'Employee id (from list_employees).'],
            'date' => ['type' => 'string', 'description' => 'Date to mark, YYYY-MM-DD (today or earlier).'],
        ],
        'fields' => [
            'status' => ['type' => 'string', 'required' => true, 'enum' => $attendanceStatuses, 'description' => 'Attendance status. wfh = work from home.'],
            'check_in' => ['type' => 'string', 'description' => 'Check-in time, HH:MM 24h. Required when check_out is given.'],
            'check_out' => ['type' => 'string', 'description' => 'Check-out time, HH:MM 24h. Required when check_in is given.'],
            'worked_minutes' => ['type' => 'integer', 'description' => 'Worked minutes 0-1440, when entering hours without check-in/out times.'],
            'notes' => ['type' => 'string', 'description' => 'Notes shown on the day, max 255.'],
            'reason' => ['type' => 'string', 'description' => 'Reason for the change (stored in the attendance log), max 255.'],
        ],
    ],
    [
        'name' => 'clear_employee_attendance',
        'route' => 'employees.attendance.destroy',
        'title' => 'Clear attendance day',
        'description' => 'Delete the stored attendance record for one employee and date, so the day goes back to its derived status (e.g. unmarked, weekly off, holiday). The removal is logged. Not allowed for future dates or dates outside employment. Requires permission attendance.manage.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'employee' => ['type' => 'integer', 'description' => 'Employee id (from list_employees).'],
            'date' => ['type' => 'string', 'description' => 'Date to clear, YYYY-MM-DD (today or earlier).'],
        ],
        'fields' => [],
    ],
    [
        'name' => 'bulk_mark_attendance',
        'route' => 'attendance.bulk.store',
        'title' => 'Bulk mark attendance',
        'description' => 'Mark several employees with the same status (and optional times) for one date; each change is logged with reason "Bulk update". Employees not employed on that date are skipped; the flash message says how many were saved. Requires permission attendance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'date' => ['type' => 'string', 'required' => true, 'description' => 'Date, YYYY-MM-DD; cannot be in the future.'],
            'employee_ids' => ['type' => 'array', 'required' => true, 'items' => ['type' => 'integer', 'description' => 'Employee id (from list_employees or list_daily_attendance).'], 'description' => '1-500 distinct employee ids.'],
            'status' => ['type' => 'string', 'required' => true, 'enum' => $attendanceStatuses, 'description' => 'Status to set for all selected employees.'],
            'check_in' => ['type' => 'string', 'description' => 'Check-in time, HH:MM 24h. Required when check_out is given.'],
            'check_out' => ['type' => 'string', 'description' => 'Check-out time, HH:MM 24h. Required when check_in is given.'],
        ],
    ],
    [
        'name' => 'generate_attendance',
        'route' => 'attendance.generate.store',
        'title' => 'Generate attendance for a range',
        'description' => 'Generate stored attendance (days off, holidays, approved leave, and present days in automatic mode) for all employees over a date range. Ranges longer than the sync limit (default 62 days) are queued; shorter ones run immediately and report how many records were created. Audited. Requires permission attendance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'start_date' => ['type' => 'string', 'required' => true, 'description' => 'Range start, YYYY-MM-DD; not in the future.'],
            'end_date' => ['type' => 'string', 'required' => true, 'description' => 'Range end, YYYY-MM-DD; on or after start_date and not in the future.'],
        ],
    ],

    // ---------------------------------------------------------------- Work shifts
    [
        'name' => 'list_work_shifts',
        'route' => 'work-shifts.index',
        'title' => 'List work shifts',
        'description' => 'All work shifts of the company (no filters, not paginated). Returns shifts[] with id, name, start_time, end_time, break_start, break_end (HH:MM), required_minutes, break_minutes, is_active, assigned_employees (current assignments) and default_for (e.g. "Company default", "Male staff"). Requires permission attendance.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [],
    ],
    [
        'name' => 'create_work_shift',
        'route' => 'work-shifts.store',
        'title' => 'Create work shift',
        'description' => 'Add a work shift. Audited. Requires permission attendance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => $workShiftFields,
    ],
    [
        'name' => 'update_work_shift',
        'route' => 'work-shifts.update',
        'title' => 'Update work shift',
        'description' => 'Edit a work shift; send every field (call list_work_shifts first to read current values; omit both break times to remove a fixed break). Audited. Requires permission attendance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'workShift' => ['type' => 'integer', 'description' => 'Work shift id (from list_work_shifts).'],
        ],
        'fields' => $workShiftFields,
    ],
    [
        'name' => 'delete_work_shift',
        'route' => 'work-shifts.destroy',
        'title' => 'Delete work shift',
        'description' => 'Delete a work shift. Refused when the shift is a company/gender default shift or has ever been assigned to employees; mark it inactive instead. Audited. Requires permission attendance.manage.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'workShift' => ['type' => 'integer', 'description' => 'Work shift id (from list_work_shifts).'],
        ],
        'fields' => [],
    ],

    // ---------------------------------------------------------------- Weekly holidays
    [
        'name' => 'get_weekly_holidays',
        'route' => 'weekly-holidays.edit',
        'title' => 'Get weekly off days',
        'description' => 'The company\'s weekly off days. Returns days (list of day-of-week numbers, 0 = Sunday ... 6 = Saturday), workingDaysThisMonth and monthLabel. Requires permission attendance.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [],
    ],
    [
        'name' => 'update_weekly_holidays',
        'route' => 'weekly-holidays.update',
        'title' => 'Set weekly off days',
        'description' => 'Replace the company\'s weekly off days (attendance, working days and payroll follow this). Send the full list; an empty list means no weekly off. At least one day must remain a working day. Audited. Requires permission attendance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'days' => ['type' => 'array', 'required' => true, 'items' => ['type' => 'integer', 'description' => 'Day of week 0-6 (0 = Sunday, 6 = Saturday).'], 'description' => 'Distinct weekly off days, max 6 entries; must be present (may be empty).'],
        ],
    ],

    // ---------------------------------------------------------------- Holidays
    [
        'name' => 'list_holidays',
        'route' => 'holidays.index',
        'title' => 'List holidays',
        'description' => 'Company holidays for one calendar year (default current year), ordered by date. Returns year, today, holidays[] (id, name, type, type_label, description, date YYYY-MM-DD, weekday) and types[] options. Requires permission attendance.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'year' => ['type' => 'integer', 'description' => 'Year, 2000-2100 (default current year).'],
        ],
    ],
    [
        'name' => 'create_holiday',
        'route' => 'holidays.store',
        'title' => 'Create holiday',
        'description' => 'Add a company holiday. Audited. Requires permission attendance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => $holidayFields,
    ],
    [
        'name' => 'update_holiday',
        'route' => 'holidays.update',
        'title' => 'Update holiday',
        'description' => 'Edit a holiday; send every field (call list_holidays first to read current values). Audited. Requires permission attendance.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'holiday' => ['type' => 'integer', 'description' => 'Holiday id (from list_holidays).'],
        ],
        'fields' => $holidayFields,
    ],
    [
        'name' => 'delete_holiday',
        'route' => 'holidays.destroy',
        'title' => 'Delete holiday',
        'description' => 'Delete a holiday. Audited. Requires permission attendance.manage.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'holiday' => ['type' => 'integer', 'description' => 'Holiday id (from list_holidays).'],
        ],
        'fields' => [],
    ],

    // ---------------------------------------------------------------- Leaves
    [
        'name' => 'list_leaves',
        'route' => 'leaves.index',
        'title' => 'List leaves',
        'description' => 'Leave records, newest start date first, paginated 20/page. Returns leaves[] (id, employee {id, name, code}, leave_type_id, leave_type, is_paid, start_date, end_date, is_half_day, days, status, status_label, reason, notes), filters, leaveTypes[] (active), statuses[], employees[] (current, id + "name (code)") and counts.pending. Requires permission leave.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'search' => ['type' => 'string', 'description' => 'Matches employee first name, last name, employee code or email.'],
            'status' => ['type' => 'string', 'enum' => ['pending', 'approved', 'rejected', 'cancelled'], 'description' => 'Leave status.'],
            'leave_type_id' => ['type' => 'integer', 'description' => 'Leave type id (from list_leave_types).'],
            'from' => ['type' => 'string', 'description' => 'YYYY-MM-DD; leaves ending on or after this date.'],
            'to' => ['type' => 'string', 'description' => 'YYYY-MM-DD; leaves starting on or before this date.'],
            'page' => ['type' => 'integer', 'description' => 'Page number (default 1).'],
        ],
    ],
    [
        'name' => 'create_leave',
        'route' => 'leaves.store',
        'title' => 'Create leave',
        'description' => 'Record leave for an employee. Refused if it overlaps another pending/approved leave of the same employee. Created as pending unless status=approved, which approves it immediately and writes paid/unpaid leave into attendance. Audited. Requires permission leave.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => array_merge(
            ['employee_id' => ['type' => 'integer', 'required' => true, 'description' => 'Employee id (from list_employees or list_leaves employees[]).']],
            $leaveFields,
            ['status' => ['type' => 'string', 'enum' => ['pending', 'approved'], 'description' => 'Initial status (default pending).']],
        ),
    ],
    [
        'name' => 'update_leave',
        'route' => 'leaves.update',
        'title' => 'Update leave',
        'description' => 'Change type, dates, half-day, reason or notes of a leave; send every field (call list_leaves first; omitted reason/notes are cleared). Cancelled or rejected leave cannot be edited; overlapping another pending/approved leave is refused. If already approved, attendance is re-synced. Audited. Requires permission leave.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'leave' => ['type' => 'integer', 'description' => 'Leave id (from list_leaves).'],
        ],
        'fields' => $leaveFields,
    ],
    [
        'name' => 'decide_leave',
        'route' => 'leaves.decision.update',
        'title' => 'Approve / reject / cancel leave',
        'description' => 'Approve, reject or cancel a leave. Only pending leave can be approved or rejected (approving writes leave into attendance). Cancel works on pending or approved leave and removes it from attendance; cancelling an already cancelled/rejected leave does nothing. Requires permission leave.manage.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'leave' => ['type' => 'integer', 'description' => 'Leave id (from list_leaves).'],
        ],
        'fields' => [
            'decision' => ['type' => 'string', 'required' => true, 'enum' => ['approve', 'reject', 'cancel'], 'description' => 'Decision to apply.'],
        ],
    ],

    // ---------------------------------------------------------------- Leave types
    [
        'name' => 'list_leave_types',
        'route' => 'leave-types.index',
        'title' => 'List leave types',
        'description' => 'All leave types (active and inactive, no filters). Returns leaveTypes[] with id, name, code, is_paid, annual_allowance, is_active, leaves_count. Requires permission leave.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [],
    ],
    [
        'name' => 'create_leave_type',
        'route' => 'leave-types.store',
        'title' => 'Create leave type',
        'description' => 'Add a leave type. Requires permission leave.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => $leaveTypeFields,
    ],
    [
        'name' => 'update_leave_type',
        'route' => 'leave-types.update',
        'title' => 'Update leave type',
        'description' => 'Edit a leave type; send every field (call list_leave_types first to read current values). Requires permission leave.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [
            'leaveType' => ['type' => 'integer', 'description' => 'Leave type id (from list_leave_types).'],
        ],
        'fields' => $leaveTypeFields,
    ],
    [
        'name' => 'delete_leave_type',
        'route' => 'leave-types.destroy',
        'title' => 'Delete leave type',
        'description' => 'Delete a leave type. Refused when any leave has been recorded under it (mark it inactive instead). Requires permission leave.manage.',
        'read_only' => false,
        'destructive' => true,
        'params' => [
            'leaveType' => ['type' => 'integer', 'description' => 'Leave type id (from list_leave_types).'],
        ],
        'fields' => [],
    ],

    // ---------------------------------------------------------------- Leave balances
    [
        'name' => 'list_leave_balances',
        'route' => 'leave-balances.index',
        'title' => 'List leave balances',
        'description' => 'Leave balances of current employees for one year, paginated 15/page. Returns employees[] (id, name, code, department, designation, status, balances[] per active leave type: leave_type_id, name, code, is_paid, allocated, adjustment, used (approved), pending, remaining = allocated + adjustment - used), year, filters, leaveTypes[] and departments[]. Requires permission leave.view.',
        'read_only' => true,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'year' => ['type' => 'integer', 'description' => 'Year, 2000-2100 (default current year).'],
            'search' => ['type' => 'string', 'description' => 'Matches employee first name, last name, employee code or email (max 100).'],
            'department_id' => ['type' => 'integer', 'description' => 'Department id (from departments[] in this result or list_departments).'],
            'page' => ['type' => 'integer', 'description' => 'Page number (default 1).'],
        ],
    ],
    [
        'name' => 'update_leave_balance',
        'route' => 'leave-balances.update',
        'title' => 'Set leave balance',
        'description' => 'Set the allocated days and manual adjustment of one leave type for one employee and year (creates the balance row if missing). Used and pending days are always computed from leave records. Requires permission leave.manage.',
        'read_only' => false,
        'destructive' => false,
        'params' => [],
        'fields' => [
            'employee_id' => ['type' => 'integer', 'required' => true, 'description' => 'Employee id (from list_leave_balances or list_employees).'],
            'leave_type_id' => ['type' => 'integer', 'required' => true, 'description' => 'Leave type id (from list_leave_types).'],
            'year' => ['type' => 'integer', 'required' => true, 'description' => 'Year, 2000-2100.'],
            'allocated' => ['type' => 'number', 'required' => true, 'description' => 'Allocated days, 0-366 (decimals allowed).'],
            'adjustment' => ['type' => 'number', 'required' => true, 'description' => 'Manual adjustment in days, -366 to 366 (e.g. carry-forward or correction).'],
        ],
    ],
];
