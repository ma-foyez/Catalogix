import { router } from '@inertiajs/react';
import { useEffect } from 'react';

/**
 * While work is in progress, reload some props on a timer. Reverb pushes updates when it
 * runs; this keeps pages moving when it does not (or when the socket drops).
 */
export function useLivePoll(
    active: boolean,
    only: string[],
    interval = 2500,
): void {
    const keys = only.join(',');

    useEffect(() => {
        if (!active) {
            return;
        }

        const timer = setInterval(() => {
            if (document.visibilityState === 'hidden') {
                return;
            }

            router.reload({
                only: [...new Set([...keys.split(','), 'queueStalled'])].filter(
                    Boolean,
                ),
                preserveUrl: true,
                async: true,
            });
        }, interval);

        return () => clearInterval(timer);
    }, [active, keys, interval]);
}
