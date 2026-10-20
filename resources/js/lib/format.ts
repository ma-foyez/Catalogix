const taka = new Intl.NumberFormat('en-IN', { maximumFractionDigits: 2 });

/**
 * "৳ 1,250" — Indian digit grouping, as Bangladeshi shops show prices.
 */
export function formatTaka(value: number | null | undefined): string {
    return value === null || value === undefined
        ? '—'
        : `৳ ${taka.format(value)}`;
}

export function formatCount(value: number): string {
    return new Intl.NumberFormat('en-IN').format(value);
}

export function formatBytes(bytes: number | null | undefined): string {
    if (!bytes) {
        return '—';
    }

    const units = ['B', 'KB', 'MB', 'GB'];
    let size = bytes;
    let unit = 0;

    while (size >= 1024 && unit < units.length - 1) {
        size /= 1024;
        unit++;
    }

    return `${size.toFixed(unit === 0 ? 0 : 1)} ${units[unit]}`;
}

const relative = new Intl.RelativeTimeFormat('en', { numeric: 'auto' });

export function timeAgo(iso: string | null | undefined): string {
    if (!iso) {
        return '—';
    }

    const seconds = Math.round((new Date(iso).getTime() - Date.now()) / 1000);
    const steps: [Intl.RelativeTimeFormatUnit, number][] = [
        ['second', 60],
        ['minute', 60],
        ['hour', 24],
        ['day', 7],
        ['week', 4.35],
        ['month', 12],
        ['year', Infinity],
    ];

    let value = seconds;

    for (const [unit, size] of steps) {
        if (Math.abs(value) < size) {
            return relative.format(Math.round(value), unit);
        }

        value /= size;
    }

    return relative.format(Math.round(value), 'year');
}
