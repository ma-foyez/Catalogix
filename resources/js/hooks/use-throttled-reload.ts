import { router } from '@inertiajs/react';
import { useCallback, useEffect, useRef } from 'react';

/**
 * Reload some Inertia props at most once per interval, however many live events arrive.
 */
export function useThrottledReload(
    only: string[],
    interval = 1500,
): () => void {
    const timer = useRef<ReturnType<typeof setTimeout> | null>(null);
    const keys = only.join(',');

    useEffect(
        () => () => {
            if (timer.current) {
                clearTimeout(timer.current);
            }
        },
        [],
    );

    return useCallback(() => {
        if (timer.current) {
            return;
        }

        timer.current = setTimeout(() => {
            timer.current = null;
            router.reload({ only: keys.split(','), preserveUrl: true });
        }, interval);
    }, [keys, interval]);
}
