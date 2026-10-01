/** A Laravel length-aware paginator, as Inertia receives it. */
export type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    from: number | null;
    to: number | null;
    total: number;
    links: { url: string | null; label: string; active: boolean }[];
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type Option<TValue extends string | number = string> = {
    value: TValue;
    label: string;
};

export type IdName = { id: number; name: string };

/** Meaning colors. Use the same tone for the same meaning everywhere. */
export type Tone = 'positive' | 'warning' | 'negative' | 'info' | 'neutral';

export type EmployeeBrief = {
    id: number;
    name: string;
    code: string;
    photo_url: string | null;
    department: string | null;
    designation: string | null;
    status: string;
};

export type AttendanceStatusOption = Option & { code: string };

/** One resolved day of an employee's attendance. */
export type AttendanceDay = {
    date: string;
    day: number;
    weekday: string;
    holiday_name: string | null;
    is_working_day: boolean;
    /** recorded | derived | unmarked | upcoming | not_employed */
    state: string;
    status: string | null;
    status_label: string | null;
    code: string | null;
    check_in: string | null;
    check_out: string | null;
    required_minutes: number;
    worked_minutes: number;
    short_minutes: number;
    overtime_minutes: number;
    late_minutes: number;
    work_shift_id: number | null;
    notes: string | null;
    source: string | null;
    attendance_id: number | null;
};

export type AttendanceSummary = {
    calendar_days: number;
    employed_days: number;
    working_days: number;
    elapsed_working_days: number;
    present: number;
    absent: number;
    half_day: number;
    paid_leave: number;
    unpaid_leave: number;
    weekly_off: number;
    holidays: number;
    wfh: number;
    late: number;
    short_hours_days: number;
    other: number;
    unmarked: number;
    upcoming: number;
    not_employed_days: number;
    not_employed_working_days: number;
    required_minutes: number;
    worked_minutes: number;
    short_minutes: number;
    overtime_minutes: number;
    attendance_rate: number;
};

/** One line of a payroll breakdown: what it is, which total it belongs to and why. */
export type PayrollLine = {
    code: string;
    label: string;
    bucket: string;
    amount: number;
    note: string;
    meta: Record<string, unknown>;
};

export type PayrollBucketAmounts = {
    label: string;
    system: number;
    adjustment: number;
    final: number;
};

export type PayrollTotals = {
    total_earnings: number;
    total_deductions: number;
    net_salary: number;
    borrow_given: number;
    net_payable: number;
};
