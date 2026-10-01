import type { IdName, Option, Tone } from '@/types/hrms';

export type DashboardFilters = {
    preset: 'current_month' | 'previous_month' | 'custom';
    from: string;
    to: string;
    department_id: number | null;
    employee_id: number | null;
    employee_status: string | null;
};

export type DashboardOptions = {
    departments: IdName[];
    employees: IdName[];
    statuses: Option[];
};

export type DashboardKpi = {
    key: string;
    label: string;
    value: number;
    format: 'number' | 'money';
    hint: string;
    delta: { value: number; direction: string; label: string } | null;
    tone: Tone;
    href: string | null;
    /** Monthly values, oldest first, for a sparkline; null when there are fewer than two. */
    trend: number[] | null;
    /** A part of a whole, e.g. present out of all active employees. */
    progress: { value: number; max: number; label: string; tone: Tone } | null;
};

export type LabelValue = { label: string; value: number };

export type RankingRow = {
    employee_id: number;
    name: string;
    code: string;
    value: number;
    detail?: string;
};

export type Ranking = {
    key: string;
    title: string;
    /** Exactly what is being measured, shown under the title. */
    metric: string;
    format: 'percent' | 'minutes' | 'days' | 'money';
    rows: RankingRow[];
};

export type DashboardAttendance = {
    overview: { key: string; label: string; value: number }[];
    late_days: number;
    rate: number | null;
    rate_explanation: string;
    hours: {
        required_minutes: number;
        worked_minutes: number;
        short_minutes: number;
        overtime_minutes: number;
    };
    hours_by_department: {
        label: string;
        worked: number;
        short: number;
        overtime: number;
    }[];
    rankings: Ranking[];
};

export type HoursTrendPoint = {
    month: string;
    label: string;
    overtime_minutes: number;
    short_minutes: number;
};

export type DashboardPayroll = {
    current: {
        id: number;
        label: string;
        status: string;
        status_label: string;
        employees: number;
        gross: number;
        overtime: number;
        borrow_given: number;
        net_payable: number;
    } | null;
    by_department: (LabelValue & { employees: number })[];
    deductions: { key: string; label: string; value: number }[];
    trend: {
        month: string;
        label: string;
        gross: number;
        net: number;
        overtime: number;
    }[];
};

export type DashboardBorrow = {
    total_borrowed: number;
    total_recovered: number;
    outstanding: number;
    employees_with_borrow: number;
    recovered_this_month: number;
    highest_outstanding: RankingRow[];
    trend: { month: string; label: string; given: number; recovered: number }[];
};

export type PersonEvent = {
    id: number;
    name: string;
    code: string;
    department: string | null;
    date: string | null;
};

export type DashboardPeople = {
    headcount: {
        active: number;
        past: number;
        new_this_month: number;
        exited_this_month: number;
        on_probation: number;
        on_notice: number;
    };
    by_department: LabelValue[];
    by_gender: LabelValue[];
    new_joiners: PersonEvent[];
    upcoming_probation: PersonEvent[];
    recent_exits: PersonEvent[];
};

export type DashboardCalendar = {
    upcoming_holidays: {
        name: string;
        date: string;
        weekday: string;
        in_days: number;
    }[];
    upcoming_weekly_offs: { date: string; weekday: string }[];
    month_label: string;
    working_days: number;
    remaining_working_days: number;
};

export type ActivityEntry = {
    id: number;
    action: string;
    description: string | null;
    user: string | null;
    employee: string | null;
    created_at: string | null;
};
