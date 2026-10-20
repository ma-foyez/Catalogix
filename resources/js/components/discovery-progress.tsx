import { Check, Clock } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Spinner } from '@/components/ui/spinner';
import { formatCount } from '@/lib/format';
import { cn } from '@/lib/utils';
import type { Discovery, DiscoveryStage } from '@/types/catalogix';

type Step = {
    stage: DiscoveryStage;
    verb: string;
    title: string;
    detail: (discovery: Discovery) => string;
};

const STEPS: Step[] = [
    {
        stage: 'waiting',
        verb: 'Queued',
        title: 'Waiting for a worker',
        detail: () => 'Starts as soon as a queue worker is free.',
    },
    {
        stage: 'home',
        verb: 'Connecting',
        title: 'Opening the home page',
        detail: () =>
            'Checking the site is reachable and not behind a bot check.',
    },
    {
        stage: 'sitemaps',
        verb: 'Searching',
        title: 'Reading sitemaps',
        detail: () => 'robots.txt, sitemap.xml and sitemap indexes.',
    },
    {
        stage: 'catalogue',
        verb: 'Searching',
        title: 'Reading the store catalogue',
        detail: (d) =>
            `WooCommerce or Shopify category lists · ${formatCount(d.candidates)} candidate pages so far.`,
    },
    {
        stage: 'menu',
        verb: 'Thinking',
        title: 'Following the menu',
        detail: (d) =>
            `Two levels of navigation · ${formatCount(d.candidates)} candidate pages so far.`,
    },
    {
        stage: 'probing',
        verb: 'Analysing',
        title: 'Checking pages for products',
        detail: (d) =>
            d.to_probe > 0
                ? `${formatCount(d.probed)} of ${formatCount(d.to_probe)} pages checked, one at a time.`
                : 'Scoring each candidate page.',
    },
    {
        stage: 'rendering',
        verb: 'Rendering',
        title: 'Trying JavaScript rendering',
        detail: () =>
            'No page showed products as plain HTML, so the best few are opened in a browser.',
    },
];

const ORDER: DiscoveryStage[] = [...STEPS.map((s) => s.stage), 'finished'];

function useElapsed(since: string | null): string {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        const timer = setInterval(() => setNow(Date.now()), 1000);

        return () => clearInterval(timer);
    }, []);

    if (!since) {
        return '0:00';
    }

    const seconds = Math.max(
        0,
        Math.floor((now - new Date(since).getTime()) / 1000),
    );

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;
}

export function currentStep(discovery: Discovery): Step {
    return STEPS.find((step) => step.stage === discovery.stage) ?? STEPS[0];
}

/**
 * Full step list for the routes page while discovery runs.
 */
export function DiscoveryProgress({
    discovery,
    stalled,
}: {
    discovery: Discovery;
    stalled: boolean;
}) {
    const current = ORDER.indexOf(discovery.stage);
    const active = currentStep(discovery);
    const elapsed = useElapsed(discovery.started_at ?? discovery.created_at);
    const steps = STEPS.filter(
        (step) => step.stage !== 'rendering' || discovery.stage === 'rendering',
    );
    const probePercent =
        discovery.to_probe > 0
            ? Math.round((discovery.probed / discovery.to_probe) * 100)
            : 0;

    return (
        <section
            aria-live="polite"
            className="overflow-hidden rounded-xl border bg-card"
        >
            <div className="relative flex flex-wrap items-center gap-4 border-b bg-linear-to-r from-primary/10 via-primary/5 to-transparent px-5 py-4">
                <span className="relative flex size-10 items-center justify-center rounded-full bg-primary/15 text-primary">
                    {stalled ? (
                        <Clock className="size-5" />
                    ) : (
                        <>
                            <span className="absolute inset-0 animate-ping rounded-full bg-primary/20" />
                            <Spinner className="size-5" />
                        </>
                    )}
                </span>
                <div className="min-w-0 flex-1">
                    <p className="text-xs font-medium tracking-wide text-primary uppercase">
                        {stalled ? 'Waiting' : active.verb}
                    </p>
                    <p className="truncate font-medium">
                        {stalled
                            ? 'No worker has picked this up yet'
                            : `${active.title}…`}
                    </p>
                </div>
                <span
                    suppressHydrationWarning
                    className="font-mono text-sm text-muted-foreground tabular-nums"
                >
                    {elapsed}
                </span>
            </div>

            <ol className="grid gap-px bg-border sm:grid-cols-2 lg:grid-cols-3">
                {steps.map((step) => {
                    const index = ORDER.indexOf(step.stage);
                    const state =
                        index < current
                            ? 'done'
                            : index === current
                              ? 'active'
                              : 'upcoming';

                    return (
                        <li
                            key={step.stage}
                            className={cn(
                                'flex gap-3 bg-card px-5 py-3.5 transition-colors',
                                state === 'active' && 'bg-primary/5',
                            )}
                        >
                            <span
                                className={cn(
                                    'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border text-[10px]',
                                    state === 'done' &&
                                        'border-success bg-success text-success-foreground',
                                    state === 'active' &&
                                        'border-primary text-primary',
                                    state === 'upcoming' &&
                                        'text-muted-foreground',
                                )}
                            >
                                {state === 'done' && (
                                    <Check className="size-3" />
                                )}
                                {state === 'active' &&
                                    (stalled ? (
                                        <Clock className="size-3" />
                                    ) : (
                                        <Spinner className="size-3" />
                                    ))}
                            </span>
                            <div className="min-w-0 space-y-0.5">
                                <p
                                    className={cn(
                                        'text-sm',
                                        state === 'upcoming'
                                            ? 'text-muted-foreground'
                                            : 'font-medium',
                                    )}
                                >
                                    {step.title}
                                </p>
                                {state === 'active' && (
                                    <p className="text-xs text-muted-foreground">
                                        {stalled && step.stage === 'waiting'
                                            ? 'No queue worker is running — see the note above.'
                                            : step.detail(discovery)}
                                    </p>
                                )}
                                {state === 'active' &&
                                    step.stage === 'probing' &&
                                    discovery.to_probe > 0 && (
                                        <div
                                            className="mt-1.5 h-1 w-40 overflow-hidden rounded-full bg-muted"
                                            role="progressbar"
                                            aria-valuenow={probePercent}
                                            aria-valuemin={0}
                                            aria-valuemax={100}
                                            aria-label="Pages checked"
                                        >
                                            <div
                                                className="h-full rounded-full bg-primary transition-[width] duration-500"
                                                style={{
                                                    width: `${Math.max(probePercent, 4)}%`,
                                                }}
                                            />
                                        </div>
                                    )}
                            </div>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}

/**
 * One-line version for lists: spinner, verb and what is happening.
 */
export function DiscoveryActivity({
    discovery,
    stalled,
}: {
    discovery: Discovery;
    stalled: boolean;
}) {
    const step = currentStep(discovery);

    if (stalled && discovery.stage === 'waiting') {
        return (
            <span className="flex items-center gap-1.5 text-xs text-warning">
                <Clock className="size-3" /> Waiting for a queue worker
            </span>
        );
    }

    return (
        <span className="flex min-w-0 items-center gap-1.5 text-xs text-primary">
            <Spinner className="size-3 shrink-0" />
            <span className="font-medium">{step.verb}</span>
            <span className="truncate text-muted-foreground">
                {step.title}
                {discovery.stage === 'probing' && discovery.to_probe > 0
                    ? ` · ${discovery.probed}/${discovery.to_probe}`
                    : '…'}
            </span>
        </span>
    );
}
