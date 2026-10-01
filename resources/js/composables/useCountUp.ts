import type { Ref } from 'vue';
import { onBeforeUnmount, ref, watch } from 'vue';

const prefersReducedMotion = (): boolean =>
    typeof window !== 'undefined' &&
    window.matchMedia('(prefers-reduced-motion: reduce)').matches;

/**
 * A number that glides to each new value instead of jumping, for figures
 * that change when filters change. A new value interrupts a running
 * animation and continues from wherever it had got to. With reduced motion
 * the number simply changes.
 */
export function useCountUp(source: () => number, duration = 600): Ref<number> {
    const display = ref(0);
    let frame = 0;

    watch(
        source,
        (target) => {
            cancelAnimationFrame(frame);

            if (!Number.isFinite(target) || prefersReducedMotion()) {
                display.value = target;

                return;
            }

            const from = display.value;
            const start = performance.now();

            const step = (now: number): void => {
                const progress = Math.min(1, (now - start) / duration);
                const eased = 1 - Math.pow(1 - progress, 3);

                display.value = from + (target - from) * eased;

                if (progress < 1) {
                    frame = requestAnimationFrame(step);
                }
            };

            frame = requestAnimationFrame(step);
        },
        { immediate: true },
    );

    onBeforeUnmount(() => cancelAnimationFrame(frame));

    return display;
}
