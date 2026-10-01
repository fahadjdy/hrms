import { usePage } from '@inertiajs/vue3';
import { computed } from 'vue';

const MONTHS = [
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
];

type MoneyOptions = {
    /** Drop the decimals, for headline figures. */
    whole?: boolean;
    /** Always show a + or - sign. */
    signed?: boolean;
};

/** Parse a `Y-m-d` date without letting the browser shift it by timezone. */
function parseDate(value: string): Date | null {
    const match = /^(\d{4})-(\d{2})-(\d{2})/.exec(value);

    if (!match) {
        const parsed = new Date(value);

        return Number.isNaN(parsed.getTime()) ? null : parsed;
    }

    return new Date(Number(match[1]), Number(match[2]) - 1, Number(match[3]));
}

/**
 * Formatting that follows the company's currency and date format, so every
 * amount, date and duration reads the same way on every screen.
 */
export function useFormat() {
    const page = usePage();
    const currency = computed(() => page.props.company?.currency ?? 'INR');
    const dateFormat = computed(
        () => page.props.company?.date_format ?? 'd M Y',
    );
    // Indian grouping (5,20,000) for rupees; standard grouping otherwise.
    const locale = computed(() =>
        currency.value === 'INR' ? 'en-IN' : 'en-US',
    );

    function money(
        amount: number | string | null | undefined,
        options: MoneyOptions = {},
    ): string {
        const value = Number(amount ?? 0);
        const digits = options.whole ? 0 : 2;

        const formatted = new Intl.NumberFormat(locale.value, {
            style: 'currency',
            currency: currency.value,
            minimumFractionDigits: digits,
            maximumFractionDigits: digits,
        }).format(Math.abs(value));

        if (value < 0) {
            return `-${formatted}`;
        }

        return options.signed && value > 0 ? `+${formatted}` : formatted;
    }

    function number(
        value: number | string | null | undefined,
        maximumFractionDigits = 1,
    ): string {
        return new Intl.NumberFormat(locale.value, {
            maximumFractionDigits,
        }).format(Number(value ?? 0));
    }

    function date(value: string | null | undefined): string {
        if (!value) {
            return '-';
        }

        const parsed = parseDate(value);

        if (!parsed) {
            return value;
        }

        const day = String(parsed.getDate()).padStart(2, '0');
        const month = String(parsed.getMonth() + 1).padStart(2, '0');
        const year = String(parsed.getFullYear());

        switch (dateFormat.value) {
            case 'd/m/Y':
                return `${day}/${month}/${year}`;
            case 'm/d/Y':
                return `${month}/${day}/${year}`;
            case 'Y-m-d':
                return `${year}-${month}-${day}`;
            case 'd-m-Y':
                return `${day}-${month}-${year}`;
            default:
                return `${day} ${MONTHS[parsed.getMonth()]} ${year}`;
        }
    }

    /** A timestamp, shown as the company's date plus the local time. */
    function dateTime(value: string | null | undefined): string {
        if (!value) {
            return '-';
        }

        const parsed = new Date(value);

        if (Number.isNaN(parsed.getTime())) {
            return value;
        }

        const local = `${parsed.getFullYear()}-${String(parsed.getMonth() + 1).padStart(2, '0')}-${String(parsed.getDate()).padStart(2, '0')}`;
        const time = parsed.toLocaleTimeString(locale.value, {
            hour: '2-digit',
            minute: '2-digit',
        });

        return `${date(local)}, ${time}`;
    }

    /** `2026-09` as "September 2026". */
    function monthLabel(value: string): string {
        const parsed = parseDate(`${value}-01`);

        return parsed
            ? parsed.toLocaleDateString('en-US', {
                  month: 'long',
                  year: 'numeric',
              })
            : value;
    }

    /** Minutes as hours and minutes: 429 becomes "7h 09m". */
    function minutes(value: number | null | undefined): string {
        const total = Math.round(Number(value ?? 0));
        const sign = total < 0 ? '-' : '';
        const absolute = Math.abs(total);

        return `${sign}${Math.floor(absolute / 60)}h ${String(absolute % 60).padStart(2, '0')}m`;
    }

    /** A 24-hour `HH:MM` time as "9:32 AM". */
    function time(value: string | null | undefined): string {
        if (!value) {
            return '-';
        }

        const [hours, mins] = value.split(':').map(Number);
        const suffix = hours >= 12 ? 'PM' : 'AM';

        return `${hours % 12 === 0 ? 12 : hours % 12}:${String(mins).padStart(2, '0')} ${suffix}`;
    }

    function percent(value: number | null | undefined): string {
        return `${number(value, 1)}%`;
    }

    return {
        currency,
        money,
        number,
        date,
        dateTime,
        monthLabel,
        minutes,
        time,
        percent,
    };
}
