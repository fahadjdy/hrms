import type { Tone } from '@/types';

/** Badge classes for each meaning color. */
export const toneClasses: Record<Tone, string> = {
    positive: 'bg-positive-soft text-positive',
    warning: 'bg-warning-soft text-warning',
    negative: 'bg-negative-soft text-negative',
    info: 'bg-info-soft text-info',
    neutral: 'bg-muted text-muted-foreground',
};

/**
 * Attendance statuses. Eleven statuses need more than five tones, so each has
 * its own chip color; the short code (P, A, HD…) is always shown with it, so
 * the color is never the only way to tell them apart.
 */
export const attendanceClasses: Record<string, string> = {
    present: 'bg-positive-soft text-positive',
    absent: 'bg-negative-soft text-negative',
    half_day: 'bg-warning-soft text-warning',
    paid_leave: 'bg-info-soft text-info',
    unpaid_leave:
        'bg-violet-100 text-violet-800 dark:bg-violet-950 dark:text-violet-300',
    holiday:
        'bg-fuchsia-100 text-fuchsia-800 dark:bg-fuchsia-950 dark:text-fuchsia-300',
    weekly_off: 'bg-muted text-muted-foreground',
    wfh: 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950 dark:text-cyan-300',
    late: 'bg-orange-100 text-orange-800 dark:bg-orange-950 dark:text-orange-300',
    short_hours:
        'bg-yellow-100 text-yellow-800 dark:bg-yellow-950 dark:text-yellow-300',
    other: 'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300',
};

export function attendanceClass(status: string | null | undefined): string {
    return status
        ? (attendanceClasses[status] ?? toneClasses.neutral)
        : 'bg-transparent text-muted-foreground';
}

const tones: Record<string, Record<string, Tone>> = {
    employee: {
        active: 'positive',
        probation: 'info',
        notice: 'warning',
        past: 'neutral',
    },
    payroll: {
        draft: 'neutral',
        calculated: 'info',
        under_review: 'warning',
        adjusted: 'warning',
        finalized: 'positive',
    },
    borrow: {
        pending_disbursement: 'info',
        active: 'warning',
        recovered: 'positive',
        cancelled: 'neutral',
    },
    leave: {
        pending: 'warning',
        approved: 'positive',
        rejected: 'negative',
        cancelled: 'neutral',
    },
    overtime: {
        pending: 'warning',
        approved: 'info',
        rejected: 'negative',
        paid: 'positive',
    },
    settlement: {
        draft: 'warning',
        finalized: 'info',
        paid: 'positive',
    },
    installment: {
        pending: 'warning',
        paid: 'positive',
    },
};

/** The meaning color for a status value of the given kind. */
export function statusTone(kind: keyof typeof tones, status: string): Tone {
    return tones[kind]?.[status] ?? 'neutral';
}
