import { Form, Head, Link, router, usePage } from '@inertiajs/react';
import {
    AlertTriangle,
    Ban,
    Check,
    Clock,
    Download,
    Eye,
    Globe,
    ImageIcon,
    ListChecks,
    MoreHorizontal,
    Package,
    RefreshCw,
    Sparkles,
    Trash2,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import DiscoveryController from '@/actions/App/Http/Controllers/DiscoveryController';
import { CollectionControls } from '@/components/collection-controls';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { PaginationLinks } from '@/components/pagination-links';
import { ToneBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Spinner } from '@/components/ui/spinner';
import { useExcelDownload } from '@/hooks/use-excel-download';
import { useLivePoll } from '@/hooks/use-live-poll';
import { formatCount, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { discover } from '@/routes';
import { show as discoveryShow } from '@/routes/discoveries';
import { show as jobShow } from '@/routes/jobs';
import type { Paginated, RunPhase, SiteRun, CollectionConfiguration } from '@/types/catalogix';

const PHASES: Record<
    RunPhase,
    {
        label: string;
        tone: 'neutral' | 'info' | 'success' | 'warning' | 'danger' | 'brand';
        active: boolean;
    }
> = {
    paused: { label: 'Paused', tone: 'warning', active: false },
    waiting: { label: 'Waiting', tone: 'neutral', active: true },
    finding: { label: 'Finding products', tone: 'brand', active: true },
    collecting: { label: 'Collecting products', tone: 'brand', active: true },
    images: { label: 'Saving pictures', tone: 'info', active: true },
    ready: { label: 'Ready', tone: 'success', active: false },
    review: { label: 'Needs your choice', tone: 'warning', active: false },
    empty: { label: 'No products found', tone: 'warning', active: false },
    blocked: { label: 'Website blocked us', tone: 'warning', active: false },
    failed: { label: "Couldn't finish", tone: 'danger', active: false },
};

const STEPS: { label: string; phases: RunPhase[] }[] = [
    { label: 'Find pages', phases: ['waiting', 'finding'] },
    { label: 'Collect products', phases: ['collecting'] },
    { label: 'Save pictures', phases: ['images'] },
    { label: 'Ready', phases: ['ready'] },
];

/** Mirrors the server's reading of pasted text so people see what will be added. */
function readDomains(text: string): { domains: string[]; invalid: string[] } {
    const domains: string[] = [];
    const invalid: string[] = [];

    for (const raw of text.split(/[\s,;|]+/)) {
        const token = raw.replace(/^["'<([]+|["'>)\]]+$/g, '');

        if (!token) {
            continue;
        }

        try {
            const host = new URL(
                /^https?:\/\//i.test(token) ? token : `https://${token}`,
            ).hostname
                .toLowerCase()
                .replace(/^www\./, '');

            if (/^[a-z0-9.-]+\.[a-z]{2,}$/.test(host)) {
                if (!domains.includes(host)) {
                    domains.push(host);
                }

                continue;
            }
        } catch {
            // falls through to invalid
        }

        invalid.push(token);
    }

    return { domains, invalid };
}

/*
 * Design brief
 * User: a seller or buyer who wants another shop's product list — not technical.
 * Main task: paste one or more website addresses, then come back to products and an Excel file.
 * Key information: per website — what is happening now in plain words, progress, how many
 * products and pictures, and when it is ready.
 * Primary action: "Collect products"; per ready site "View products" and "Download Excel".
 * States: empty, pasting (live preview of what will be added), invalid input, working (live),
 * ready, nothing found, blocked, failed, waiting for the background worker.
 */
export default function Websites({ runs, configuration }: { runs: Paginated<SiteRun>; configuration: CollectionConfiguration }) {
    const { queueStalled } = usePage().props;
    const [text, setText] = useState('');
    const preview = useMemo(() => readDomains(text), [text]);
    const active = runs.data.some((run) => PHASES[run.phase].active);
    const excel = useExcelDownload();

    useLivePoll(active, ['runs'], 2500);

    return (
        <>
            <Head title="Websites" />

            <div className="mx-auto flex w-full max-w-5xl flex-col gap-8 px-4 py-8 md:py-10">
                <section className="relative overflow-hidden rounded-2xl border bg-card px-5 py-7 md:px-8 md:py-9">
                    <div
                        aria-hidden
                        className="pointer-events-none absolute -top-24 -right-24 size-72 rounded-full bg-primary/15 blur-3xl"
                    />

                    <div className="relative space-y-5">
                        <div className="space-y-2">
                            <h1 className="text-2xl font-semibold tracking-tight text-balance md:text-3xl">
                                Collect products from any website
                            </h1>
                            <p className="max-w-2xl text-sm text-muted-foreground md:text-base">
                                Paste one or more shop addresses. Catalogix
                                finds their products, saves every picture and
                                gets an Excel file ready — you don't need to do
                                anything else.
                            </p>
                        </div>

                        <Form
                            {...DiscoveryController.store.form()}
                            className="space-y-2"
                            disableWhileProcessing
                            onSuccess={() => setText('')}
                        >
                            {({ processing, errors, clearErrors }) => (
                                <>
                                    <Label
                                        htmlFor="domains"
                                        className="sr-only"
                                    >
                                        Website addresses
                                    </Label>
                                    <div className="rounded-xl border bg-background shadow-sm focus-within:ring-2 focus-within:ring-ring/40">
                                        <textarea
                                            id="domains"
                                            name="domains"
                                            value={text}
                                            onChange={(event) => {
                                                setText(event.target.value);
                                                clearErrors('domains');
                                            }}
                                            onKeyDown={(event) => {
                                                if (
                                                    event.key === 'Enter' &&
                                                    !event.shiftKey
                                                ) {
                                                    event.preventDefault();
                                                    event.currentTarget.form?.requestSubmit();
                                                }
                                            }}
                                            rows={2}
                                            required
                                            autoFocus
                                            aria-invalid={
                                                errors.domains
                                                    ? true
                                                    : undefined
                                            }
                                            placeholder="chaldal.com, https://www.rflbd.com, bongofurniture.com"
                                            className="block max-h-48 min-h-14 w-full resize-y rounded-t-xl bg-transparent px-4 py-3 font-mono text-sm outline-none placeholder:text-muted-foreground"
                                        />
                                        <div className="flex flex-wrap items-center gap-2 border-t px-3 py-2">
                                            <div className="flex min-w-0 flex-1 flex-wrap items-center gap-1.5 text-xs text-muted-foreground">
                                                {preview.domains.length === 0 &&
                                                preview.invalid.length === 0 ? (
                                                    <span>
                                                        Full links are fine. Put
                                                        several on new lines or
                                                        separate them with
                                                        commas (up to{' '}
                                                        {configuration.max_websites}).
                                                    </span>
                                                ) : (
                                                    <>
                                                        {preview.domains.map(
                                                            (domain) => (
                                                                <span
                                                                    key={domain}
                                                                    className="inline-flex items-center gap-1 rounded-md border bg-muted/60 px-1.5 py-0.5 font-mono text-foreground"
                                                                >
                                                                    <Check className="size-3 text-success" />
                                                                    {domain}
                                                                </span>
                                                            ),
                                                        )}
                                                        {preview.invalid.map(
                                                            (token) => (
                                                                <span
                                                                    key={token}
                                                                    className="inline-flex max-w-48 items-center gap-1 truncate rounded-md border border-destructive/40 px-1.5 py-0.5 font-mono text-destructive"
                                                                    title="Not a website address"
                                                                >
                                                                    {token}
                                                                </span>
                                                            ),
                                                        )}
                                                    </>
                                                )}
                                            </div>
                                            <Button
                                                type="submit"
                                                disabled={
                                                    processing ||
                                                    preview.domains.length === 0
                                                }
                                            >
                                                {processing ? (
                                                    <Spinner />
                                                ) : (
                                                    <Sparkles />
                                                )}
                                                {preview.domains.length > 1
                                                    ? `Collect from ${preview.domains.length} websites`
                                                    : 'Collect products'}
                                            </Button>
                                        </div>
                                    </div>
                                    <InputError message={errors.domains} />
                                    <div className="flex flex-wrap items-center gap-3">
                                        <Label htmlFor="max_products">
                                            Maximum products per website
                                        </Label>
                                        <Input
                                            id="max_products"
                                            name="max_products"
                                            type="number"
                                            min={configuration.min_products}
                                            max={configuration.max_products ?? 100000}
                                            step={1}
                                            placeholder="No limit"
                                            defaultValue={configuration.max_products ?? ''}
                                            className="w-36"
                                        />
                                        <span className="text-xs text-muted-foreground">
                                            {configuration.max_products !== null ? `Your configured maximum is ${formatCount(configuration.max_products)} products.` : 'Leave blank for no product cap.'} <Link href="/settings/configuration" className="text-primary hover:underline">Manage defaults</Link>
                                        </span>
                                    </div>
                                    <InputError message={errors.max_products} />
                                </>
                            )}
                        </Form>

                        <p className="text-xs text-muted-foreground">
                            Catalogix visits one page at a time and respects
                            each site's rules. Pages behind a login or a robot
                            check are skipped, never forced.
                        </p>
                    </div>
                </section>

                <section className="space-y-3">
                    <div className="flex items-center justify-between">
                        <h2 className="font-medium">Your websites</h2>
                        {active && (
                            <span className="flex items-center gap-1.5 text-xs text-muted-foreground">
                                <span className="relative flex size-2">
                                    <span className="absolute inline-flex size-full animate-ping rounded-full bg-primary opacity-60" />
                                    <span className="relative inline-flex size-2 rounded-full bg-primary" />
                                </span>
                                Updating live
                            </span>
                        )}
                    </div>

                    {runs.data.length === 0 ? (
                        <EmptyState
                            icon={Globe}
                            title="No websites yet"
                            description="Paste a shop's address above. It shows up here while its products are collected."
                        />
                    ) : (
                        <ul className="space-y-3">
                            {runs.data.map((run) => (
                                <SiteCard
                                    key={run.id}
                                    run={run}
                                    stalled={
                                        run.phase === 'waiting' &&
                                        (queueStalled?.queues.includes(
                                            'discovery',
                                        ) ??
                                            false)
                                    }
                                    preparing={
                                        run.job_id !== null &&
                                        excel.preparing(run.job_id)
                                    }
                                    onDownload={() =>
                                        run.job_id && excel.download(run.job_id)
                                    }
                                />
                            ))}
                        </ul>
                    )}

                    <PaginationLinks page={runs} only={['runs']} />
                </section>
            </div>
        </>
    );
}

function SiteCard({
    run,
    stalled,
    preparing,
    onDownload,
}: {
    run: SiteRun;
    stalled: boolean;
    preparing: boolean;
    onDownload: () => void;
}) {
    const phase = PHASES[run.phase];
    const step = STEPS.findIndex((s) => s.phases.includes(run.phase));
    const problem = ['empty', 'blocked', 'failed'].includes(run.phase);
    const canView = run.job_id !== null && run.products > 0;

    return (
        <li
            className={cn(
                'overflow-hidden rounded-xl border bg-card transition-shadow',
                phase.active && 'border-primary/30 shadow-sm shadow-primary/5',
            )}
        >
            <div className="flex flex-wrap items-start gap-4 p-4">
                <span
                    className={cn(
                        'flex size-11 shrink-0 items-center justify-center rounded-xl border text-lg font-semibold uppercase',
                        run.phase === 'ready'
                            ? 'border-success/30 bg-success/10 text-success'
                            : phase.active
                              ? 'border-primary/30 bg-primary/10 text-primary'
                              : 'bg-muted text-muted-foreground',
                    )}
                    aria-hidden
                >
                    {run.domain.charAt(0)}
                </span>

                <div className="min-w-0 flex-1 space-y-1.5">
                    <div className="flex flex-wrap items-center gap-2">
                        <a
                            href={`https://${run.domain}`}
                            target="_blank"
                            rel="noreferrer"
                            className="truncate font-mono font-medium hover:underline"
                        >
                            {run.domain}
                        </a>
                        <ToneBadge tone={stalled ? 'warning' : phase.tone}>
                            {phase.active && !stalled && (
                                <Spinner className="size-3" />
                            )}
                            {stalled ? 'Waiting for the worker' : phase.label}
                        </ToneBadge>
                    </div>

                    <p
                        className={cn(
                            'flex items-start gap-1.5 text-sm',
                            problem
                                ? 'text-muted-foreground'
                                : 'text-foreground/80',
                        )}
                    >
                        {run.phase === 'blocked' && (
                            <Ban className="mt-0.5 size-4 shrink-0 text-warning" />
                        )}
                        {['empty', 'failed'].includes(run.phase) && (
                            <AlertTriangle className="mt-0.5 size-4 shrink-0 text-warning" />
                        )}
                        {stalled && (
                            <Clock className="mt-0.5 size-4 shrink-0 text-warning" />
                        )}
                        <span suppressHydrationWarning>
                            {stalled
                                ? 'The background worker is not running yet — see the note at the top of the page.'
                                : run.detail}
                            {!phase.active && run.finished_at && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    · {timeAgo(run.finished_at)}
                                </span>
                            )}
                        </span>
                    </p>

                    {run.max_products !== null && (
                        <p className="text-xs text-muted-foreground">
                            {formatCount(run.products)} /{' '}
                            {formatCount(run.max_products)} products
                            {run.limit_reached && ' · Limit reached'}
                        </p>
                    )}

                    {(run.products > 0 || run.images > 0) && (
                        <div className="flex flex-wrap gap-x-4 gap-y-1 text-xs text-muted-foreground">
                            <span className="flex items-center gap-1">
                                <Package className="size-3.5" />
                                <span className="font-mono text-foreground">
                                    {formatCount(run.products)}
                                </span>{' '}
                                products
                            </span>
                            <span className="flex items-center gap-1">
                                <ImageIcon className="size-3.5" />
                                <span className="font-mono text-foreground">
                                    {formatCount(run.images)}
                                </span>{' '}
                                pictures
                            </span>
                            {run.categories > 0 && (
                                <span className="flex items-center gap-1">
                                    <ListChecks className="size-3.5" />
                                    <span className="font-mono text-foreground">
                                        {run.categories}
                                    </span>{' '}
                                    {run.categories === 1
                                        ? 'section'
                                        : 'sections'}
                                </span>
                            )}
                        </div>
                    )}
                </div>

                <div className="flex w-full items-center gap-2 sm:w-auto">
                    {(phase.active || run.paused) && (
                        <CollectionControls id={run.id} paused={run.paused} />
                    )}
                    {canView && (
                        <Button asChild variant="outline" size="sm">
                            <Link href={jobShow(run.job_id!)}>
                                <Eye />
                                {run.phase === 'ready'
                                    ? 'View products'
                                    : 'See so far'}
                            </Link>
                        </Button>
                    )}
                    {run.phase === 'ready' && (
                        <Button
                            size="sm"
                            onClick={onDownload}
                            disabled={preparing}
                        >
                            {preparing ? <Spinner /> : <Download />}
                            {preparing ? 'Preparing…' : 'Download Excel'}
                        </Button>
                    )}
                    {run.phase === 'review' && (
                        <Button asChild size="sm">
                            <Link href={discoveryShow(run.id)}>
                                <ListChecks /> Choose pages
                            </Link>
                        </Button>
                    )}
                    {problem && (
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    DiscoveryController.refresh.url(run.id),
                                    {},
                                    { preserveScroll: true },
                                )
                            }
                        >
                            <RefreshCw /> Try again
                        </Button>
                    )}
                    <RunMenu run={run} />
                </div>
            </div>

            {phase.active && (
                <div className="border-t bg-muted/30 px-4 py-3">
                    <ol className="mb-2 grid grid-cols-4 gap-2 text-[11px] sm:text-xs">
                        {STEPS.map((s, index) => (
                            <li
                                key={s.label}
                                className={cn(
                                    'flex items-center gap-1.5 truncate',
                                    index < step && 'text-success',
                                    index === step &&
                                        'font-medium text-primary',
                                    index > step && 'text-muted-foreground',
                                )}
                            >
                                {index < step ? (
                                    <Check className="size-3.5 shrink-0" />
                                ) : index === step && !stalled ? (
                                    <Spinner className="size-3.5 shrink-0" />
                                ) : (
                                    <span className="flex size-3.5 shrink-0 items-center justify-center rounded-full border text-[9px]">
                                        {index + 1}
                                    </span>
                                )}
                                <span className="truncate">{s.label}</span>
                            </li>
                        ))}
                    </ol>
                    <div
                        className="h-1.5 overflow-hidden rounded-full bg-muted"
                        role="progressbar"
                        aria-valuenow={run.progress}
                        aria-valuemin={0}
                        aria-valuemax={100}
                        aria-label={`${run.domain} progress`}
                    >
                        <div
                            className="h-full rounded-full bg-linear-to-r from-primary to-fuchsia-500 transition-[width] duration-700"
                            style={{ width: `${Math.max(run.progress, 3)}%` }}
                        />
                    </div>
                </div>
            )}
        </li>
    );
}

function RunMenu({ run }: { run: SiteRun }) {
    const working = PHASES[run.phase].active || run.paused;

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button
                    variant="ghost"
                    size="icon"
                    className="ml-auto size-8 sm:ml-0"
                    aria-label={`More for ${run.domain}`}
                >
                    <MoreHorizontal />
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-52">
                <DropdownMenuItem
                    disabled={working}
                    onSelect={() =>
                        router.post(
                            DiscoveryController.refresh.url(run.id),
                            {},
                            { preserveScroll: true },
                        )
                    }
                >
                    <RefreshCw /> Refresh prices & stock
                </DropdownMenuItem>
                <DropdownMenuItem asChild>
                    <Link href={discoveryShow(run.id)}>
                        <ListChecks /> Choose pages myself
                    </Link>
                </DropdownMenuItem>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                    className="text-destructive focus:text-destructive"
                    onSelect={() =>
                        router.delete(DiscoveryController.destroy.url(run.id), {
                            preserveScroll: true,
                        })
                    }
                >
                    <Trash2 /> Remove from list
                </DropdownMenuItem>
            </DropdownMenuContent>
        </DropdownMenu>
    );
}

Websites.layout = {
    breadcrumbs: [{ title: 'Websites', href: discover() }],
};
