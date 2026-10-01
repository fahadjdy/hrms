import { usePage } from '@inertiajs/vue3';
import type { ChartFormat } from '@/composables/useChartTheme';
import { useFormat } from '@/composables/useFormat';

/**
 * Value formatting for charts: full values for tooltips and tables, compact
 * values for axis ticks where space is tight.
 */
export function useChartFormat() {
    const page = usePage();
    const { money, number, minutes, percent } = useFormat();

    function full(value: number, format: ChartFormat): string {
        switch (format) {
            case 'money':
                return money(value);
            case 'minutes':
                return minutes(value);
            case 'percent':
                return percent(value);
            case 'days':
                return `${number(value)} day${value === 1 ? '' : 's'}`;
            default:
                return number(value);
        }
    }

    function compact(value: number, format: ChartFormat): string {
        const currency = page.props.company?.currency ?? 'INR';
        const locale = currency === 'INR' ? 'en-IN' : 'en-US';

        switch (format) {
            case 'money':
                return new Intl.NumberFormat(locale, {
                    style: 'currency',
                    currency,
                    notation: 'compact',
                    maximumFractionDigits: 1,
                }).format(value);
            case 'minutes':
                return `${Math.round(value / 60)}h`;
            case 'percent':
                return `${Math.round(value)}%`;
            default:
                return new Intl.NumberFormat(locale, {
                    notation: 'compact',
                    maximumFractionDigits: 1,
                }).format(value);
        }
    }

    return { full, compact };
}
