import { cva, type VariantProps } from 'class-variance-authority';
import { cn } from '@/lib/utils';

const statusBadgeVariants = cva(
    'inline-flex items-center gap-1.5 rounded-md border px-2 py-0.5 text-xs font-medium whitespace-nowrap',
    {
        variants: {
            tone: {
                neutral: 'border-border bg-muted text-muted-foreground',
                info: 'border-info/30 bg-info/10 text-info',
                success: 'border-success/30 bg-success/10 text-success',
                warning: 'border-warning/30 bg-warning/10 text-warning',
                danger: 'border-destructive/30 bg-destructive/10 text-destructive dark:text-destructive-foreground',
                brand: 'border-primary/30 bg-primary/10 text-primary',
            },
        },
        defaultVariants: { tone: 'neutral' },
    },
);

const dotVariants = cva('size-1.5 rounded-full', {
    variants: {
        tone: {
            neutral: 'bg-muted-foreground',
            info: 'bg-info',
            success: 'bg-success',
            warning: 'bg-warning',
            danger: 'bg-destructive',
            brand: 'bg-primary',
        },
        pulse: { true: 'animate-pulse', false: '' },
    },
    defaultVariants: { tone: 'neutral', pulse: false },
});

type Tone = NonNullable<VariantProps<typeof statusBadgeVariants>['tone']>;

const STATUS: Record<string, { tone: Tone; label: string; live?: boolean }> = {
    queued: { tone: 'neutral', label: 'Queued' },
    running: { tone: 'info', label: 'Running', live: true },
    crawling: { tone: 'info', label: 'Crawling', live: true },
    processing: { tone: 'info', label: 'Building', live: true },
    pending: { tone: 'neutral', label: 'Pending' },
    done: { tone: 'success', label: 'Done' },
    ready: { tone: 'success', label: 'Ready' },
    downloaded: { tone: 'success', label: 'Downloaded' },
    blocked: { tone: 'warning', label: 'Blocked' },
    failed: { tone: 'danger', label: 'Failed' },
};

export function StatusBadge({
    status,
    label,
    className,
}: {
    status: string;
    label?: string;
    className?: string;
}) {
    const config = STATUS[status] ?? { tone: 'neutral' as Tone, label: status };

    return (
        <span
            className={cn(
                statusBadgeVariants({ tone: config.tone }),
                className,
            )}
        >
            <span
                aria-hidden
                className={dotVariants({
                    tone: config.tone,
                    pulse: config.live ?? false,
                })}
            />
            {label ?? config.label}
        </span>
    );
}

export function ToneBadge({
    tone,
    children,
    className,
}: {
    tone: Tone;
    children: React.ReactNode;
    className?: string;
}) {
    return (
        <span className={cn(statusBadgeVariants({ tone }), className)}>
            {children}
        </span>
    );
}
