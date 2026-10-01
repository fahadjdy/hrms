import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

export type ChartFormat = 'money' | 'number' | 'minutes' | 'percent' | 'days';

/** Series slots in their fixed, colorblind-validated order. Never cycle them. */
const SERIES = [
    'chart-1',
    'chart-2',
    'chart-3',
    'chart-4',
    'chart-5',
    'chart-6',
];

function cssVariable(name: string): string {
    if (typeof document === 'undefined') {
        return '#888888';
    }

    return getComputedStyle(document.documentElement)
        .getPropertyValue(`--${name}`)
        .trim();
}

/**
 * Chart colors read from the theme's CSS variables, re-read when the user
 * switches between light and dark so charts never keep the old palette.
 */
export function useChartTheme() {
    const version = ref(0);
    let observer: MutationObserver | undefined;

    onMounted(() => {
        observer = new MutationObserver(() => version.value++);
        observer.observe(document.documentElement, {
            attributes: true,
            attributeFilter: ['class'],
        });
    });

    onBeforeUnmount(() => observer?.disconnect());

    const theme = computed(() => {
        // Depend on the version so a theme switch recomputes the colors.
        void version.value;

        return {
            series: SERIES.map(cssVariable),
            neutral: cssVariable('chart-neutral'),
            grid: cssVariable('chart-grid'),
            surface: cssVariable('card'),
            text: cssVariable('muted-foreground'),
            ink: cssVariable('foreground'),
            border: cssVariable('border'),
        };
    });

    return { theme };
}

/** `chart-2` and friends as a CSS color, for legend swatches and CSS-drawn marks. */
export function seriesColor(slot: number): string {
    return `var(--${SERIES[slot] ?? 'chart-neutral'})`;
}
