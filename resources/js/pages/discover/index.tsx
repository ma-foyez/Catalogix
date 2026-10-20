import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    FileSpreadsheet,
    Globe,
    ListChecks,
    Radar,
    ShieldCheck,
} from 'lucide-react';
import { useRef } from 'react';
import DiscoveryController from '@/actions/App/Http/Controllers/DiscoveryController';
import { DiscoveryActivity } from '@/components/discovery-progress';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { useLivePoll } from '@/hooks/use-live-poll';
import { formatCount, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { discover } from '@/routes';
import { show } from '@/routes/discoveries';
import type { Discovery } from '@/types/catalogix';

const EXAMPLES = ['chaldal.com', 'v2.sawdahexpress.com.bd', 'shajgoj.com'];

const STEPS = [
    {
        icon: Radar,
        title: 'Discover',
        text: 'Sitemaps, catalogue and menu become a scored list of product pages.',
    },
    {
        icon: ListChecks,
        title: 'Tick & extract',
        text: 'Name, price, sale price, stock and a local image for every product.',
    },
    {
        icon: FileSpreadsheet,
        title: 'Export',
        text: 'Excel with pictures in the cells, CSV or an images ZIP.',
    },
];

/*
 * Design brief
 * User: seller or sourcing agent who wants another shop's catalogue.
 * Main task: type only a domain and start route discovery.
 * Key information: what Catalogix will do next; recent discoveries and what each is doing now.
 * Primary action: "Find product routes".
 * States: empty history, validation error, submitting, recent list with live activity,
 * waiting for a worker.
 */
export default function DiscoverIndex({
    recent,
}: {
    recent: { data: Discovery[] };
}) {
    const { queueStalled } = usePage().props;
    const input = useRef<HTMLInputElement>(null);
    const active = recent.data.some(
        (d) => d.status === 'queued' || d.status === 'running',
    );
    const discoveryStalled =
        queueStalled?.queues.includes('discovery') ?? false;

    useLivePoll(active, ['recent'], 2500);

    return (
        <>
            <Head title="Discover" />

            <div className="mx-auto flex w-full max-w-4xl flex-col gap-10 px-4 py-8 md:py-12">
                <section className="relative overflow-hidden rounded-2xl border bg-card px-5 py-8 md:px-10 md:py-12">
                    <div
                        aria-hidden
                        className="pointer-events-none absolute inset-0 bg-[linear-gradient(to_right,var(--border)_1px,transparent_1px),linear-gradient(to_bottom,var(--border)_1px,transparent_1px)] mask-[radial-gradient(ellipse_at_top_right,black_10%,transparent_65%)] bg-size-[28px_28px] opacity-60"
                    />
                    <div
                        aria-hidden
                        className="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-primary/20 blur-3xl"
                    />

                    <div className="relative space-y-6">
                        <div className="space-y-3">
                            <span className="inline-flex items-center gap-1.5 rounded-full border bg-background/70 px-2.5 py-1 text-xs font-medium text-muted-foreground backdrop-blur">
                                <span className="size-1.5 rounded-full bg-primary" />
                                No AI, no paid services — just the shop's own
                                pages
                            </span>
                            <h1 className="text-2xl font-semibold tracking-tight text-balance md:text-4xl">
                                Find every product route on a site
                            </h1>
                            <p className="max-w-2xl text-sm text-muted-foreground md:text-base">
                                Enter a shop's domain. Catalogix reads its
                                sitemap, menu and catalogue, then suggests the
                                pages that list products — you tick the ones to
                                extract.
                            </p>
                        </div>

                        <Form
                            {...DiscoveryController.store.form()}
                            className="space-y-2"
                            disableWhileProcessing
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Label htmlFor="domain" className="sr-only">
                                        Domain
                                    </Label>
                                    <div className="flex flex-col gap-2 rounded-xl border bg-background p-1.5 shadow-sm focus-within:ring-2 focus-within:ring-ring/40 sm:flex-row">
                                        <div className="relative flex-1">
                                            <Globe className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                            <Input
                                                ref={input}
                                                id="domain"
                                                name="domain"
                                                placeholder="chaldal.com"
                                                autoComplete="off"
                                                autoFocus
                                                required
                                                aria-invalid={
                                                    errors.domain
                                                        ? true
                                                        : undefined
                                                }
                                                className="h-11 border-0 pl-9 font-mono text-base shadow-none focus-visible:ring-0"
                                            />
                                        </div>
                                        <Button
                                            type="submit"
                                            size="lg"
                                            className="h-11"
                                            disabled={processing}
                                        >
                                            {processing ? (
                                                <Spinner />
                                            ) : (
                                                <Radar />
                                            )}
                                            {processing
                                                ? 'Starting…'
                                                : 'Find product routes'}
                                        </Button>
                                    </div>
                                    <InputError message={errors.domain} />
                                    <div className="flex flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                        <span>Try</span>
                                        {EXAMPLES.map((example) => (
                                            <button
                                                key={example}
                                                type="button"
                                                onClick={() => {
                                                    if (input.current) {
                                                        input.current.value =
                                                            example;
                                                        input.current.focus();
                                                    }
                                                }}
                                                className="rounded-md border bg-background px-1.5 py-0.5 font-mono transition-colors hover:border-primary/40 hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                            >
                                                {example}
                                            </button>
                                        ))}
                                    </div>
                                </>
                            )}
                        </Form>

                        <ol className="grid gap-3 pt-2 sm:grid-cols-3">
                            {STEPS.map((step, index) => (
                                <li
                                    key={step.title}
                                    className="flex gap-3 rounded-xl border bg-background/60 p-3 backdrop-blur"
                                >
                                    <span className="flex size-8 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                                        <step.icon className="size-4" />
                                    </span>
                                    <div className="space-y-0.5">
                                        <p className="text-sm font-medium">
                                            <span className="font-mono text-xs text-muted-foreground">
                                                0{index + 1}
                                            </span>{' '}
                                            {step.title}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {step.text}
                                        </p>
                                    </div>
                                </li>
                            ))}
                        </ol>

                        <p className="flex items-center gap-1.5 text-xs text-muted-foreground">
                            <ShieldCheck className="size-3.5 text-success" />
                            One page at a time per site, robots.txt respected.
                            Pages behind a login, CAPTCHA or bot check are
                            marked blocked, never bypassed.
                        </p>
                    </div>
                </section>

                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-sm font-medium">
                            Recent discoveries
                        </h2>
                        {active && (
                            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <span className="relative flex size-2">
                                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-primary opacity-60" />
                                    <span className="relative inline-flex size-2 rounded-full bg-primary" />
                                </span>
                                Live
                            </span>
                        )}
                    </div>

                    {recent.data.length === 0 ? (
                        <EmptyState
                            icon={Radar}
                            title="No discoveries yet"
                            description="Your first domain will show up here with the routes it found."
                        />
                    ) : (
                        <ul className="divide-y overflow-hidden rounded-xl border bg-card">
                            {recent.data.map((discovery) => {
                                const running =
                                    discovery.status === 'queued' ||
                                    discovery.status === 'running';

                                return (
                                    <li key={discovery.id}>
                                        <Link
                                            href={show(discovery.id)}
                                            className={cn(
                                                'group flex items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none',
                                                running && 'bg-primary/3',
                                            )}
                                        >
                                            <span
                                                className={cn(
                                                    'flex size-9 shrink-0 items-center justify-center rounded-lg border',
                                                    running
                                                        ? 'border-primary/30 bg-primary/10 text-primary'
                                                        : 'bg-muted text-muted-foreground',
                                                )}
                                            >
                                                {running &&
                                                !(
                                                    discoveryStalled &&
                                                    discovery.status ===
                                                        'queued'
                                                ) ? (
                                                    <Spinner className="size-4" />
                                                ) : (
                                                    <Globe className="size-4" />
                                                )}
                                            </span>
                                            <span className="min-w-0 flex-1 space-y-0.5">
                                                <span className="block truncate font-mono text-sm">
                                                    {discovery.domain}
                                                </span>
                                                {running ? (
                                                    <DiscoveryActivity
                                                        discovery={discovery}
                                                        stalled={
                                                            discoveryStalled
                                                        }
                                                    />
                                                ) : (
                                                    <span
                                                        suppressHydrationWarning
                                                        className="block truncate text-xs text-muted-foreground"
                                                    >
                                                        {discovery.status ===
                                                        'done'
                                                            ? `${formatCount(discovery.routes_found)} product ${discovery.routes_found === 1 ? 'route' : 'routes'}`
                                                            : (discovery.error ??
                                                              'Failed')}{' '}
                                                        ·{' '}
                                                        {timeAgo(
                                                            discovery.created_at,
                                                        )}
                                                    </span>
                                                )}
                                            </span>
                                            <StatusBadge
                                                status={discovery.status}
                                                label={
                                                    discovery.status ===
                                                    'running'
                                                        ? 'Discovering'
                                                        : undefined
                                                }
                                            />
                                            <ArrowRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                                        </Link>
                                    </li>
                                );
                            })}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

DiscoverIndex.layout = {
    breadcrumbs: [{ title: 'Discover', href: discover() }],
};
