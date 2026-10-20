import { Head, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import {
    AlertTriangle,
    CheckSquare,
    Download,
    Globe,
    RefreshCw,
    Square,
} from 'lucide-react';
import { useCallback, useEffect, useMemo, useRef, useState } from 'react';
import DiscoveryController from '@/actions/App/Http/Controllers/DiscoveryController';
import { DiscoveryProgress } from '@/components/discovery-progress';
import { CollectionControls } from '@/components/collection-controls';
import { EmptyState } from '@/components/empty-state';
import { ScoreBadge } from '@/components/score-badge';
import { StatusBadge, ToneBadge } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Skeleton } from '@/components/ui/skeleton';
import { Spinner } from '@/components/ui/spinner';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useLivePoll } from '@/hooks/use-live-poll';
import { formatCount, timeAgo } from '@/lib/format';
import { cn } from '@/lib/utils';
import { discover } from '@/routes';
import type { DiscoveredRoute, Discovery } from '@/types/catalogix';

const SOURCE_LABELS: Record<DiscoveredRoute['source'], string> = {
    platform_api: 'Catalogue',
    sitemap: 'Sitemap',
    menu: 'Menu',
    pattern: 'URL pattern',
};

const PLATFORM_LABELS: Record<string, string> = {
    woocommerce: 'WooCommerce',
    shopify: 'Shopify',
    custom: 'Custom site',
};

/*
 * Design brief
 * User: the person who just typed a domain.
 * Main task: review suggested routes and tick the ones to extract.
 * Key information: what discovery is doing right now, then route path, category,
 * estimated products, confidence score and three sample thumbnails.
 * Primary action: "Extract N routes".
 * States: waiting for a worker, each discovery step (rows filling in live), done, failed,
 * blocked, nothing found.
 */
export default function DiscoverShow({
    discovery: incoming,
}: {
    discovery: Discovery;
}) {
    const { queueStalled } = usePage().props;
    const [discovery, setDiscovery] = useState<Discovery>(incoming);
    const [selected, setSelected] = useState<Set<number>>(
        () =>
            new Set(
                (incoming.routes ?? [])
                    .filter((r) => r.is_selected)
                    .map((r) => r.id),
            ),
    );
    const known = useRef(new Set((incoming.routes ?? []).map((r) => r.id)));
    const [submitting, setSubmitting] = useState(false);

    // Updates arrive from Reverb or from polling; pre-tick new high-score routes and keep the user's own choices.
    const apply = useCallback((next: Discovery) => {
        setDiscovery(next);
        setSelected((current) => {
            const ticked = new Set(current);

            (next.routes ?? []).forEach((route) => {
                if (!known.current.has(route.id)) {
                    known.current.add(route.id);

                    if (route.is_selected) {
                        ticked.add(route.id);
                    }
                }
            });

            return ticked;
        });
    }, []);

    useEffect(() => apply(incoming), [incoming, apply]);

    useEcho<Discovery>(
        `discovery.${incoming.id}`,
        '.routes.discovered',
        apply,
        [apply],
    );

    const running =
        !discovery.paused &&
        (discovery.status === 'queued' || discovery.status === 'running');
    const stalled =
        discovery.status === 'queued' &&
        (queueStalled?.queues.includes('discovery') ?? false);

    useLivePoll(running, ['discovery'], 2000);

    const routes = discovery.routes ?? [];
    const productRoutes = useMemo(
        () => routes.filter((route) => route.score > 0),
        [routes],
    );
    const highRoutes = routes.filter((route) => route.score_label === 'high');
    const totalEstimate = productRoutes.reduce(
        (sum, route) => sum + route.est_products,
        0,
    );
    const estimate = routes
        .filter((route) => selected.has(route.id))
        .reduce((sum, route) => sum + route.est_products, 0);

    const toggle = (id: number) =>
        setSelected((current) => {
            const next = new Set(current);

            if (next.has(id)) {
                next.delete(id);
            } else {
                next.add(id);
            }

            return next;
        });

    const extract = () => {
        setSubmitting(true);
        router.post(
            DiscoveryController.extract.url(discovery.id),
            { route_ids: [...selected] },
            { onFinish: () => setSubmitting(false) },
        );
    };

    return (
        <>
            <Head title={`Routes · ${discovery.domain}`} />

            <div className="flex flex-col gap-6 px-4 py-6 md:px-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="flex min-w-0 items-start gap-3">
                        <span className="flex size-11 shrink-0 items-center justify-center rounded-xl border bg-linear-to-br from-primary/15 to-primary/5 text-primary">
                            <Globe className="size-5" />
                        </span>
                        <div className="min-w-0 space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="truncate font-mono text-xl font-semibold">
                                    {discovery.domain}
                                </h1>
                                <StatusBadge
                                    status={
                                        discovery.paused
                                            ? 'paused'
                                            : discovery.status
                                    }
                                    label={
                                        !discovery.paused &&
                                        discovery.status === 'running'
                                            ? 'Discovering'
                                            : undefined
                                    }
                                />
                                {discovery.site?.platform &&
                                    discovery.site.platform !== 'unknown' && (
                                        <ToneBadge tone="neutral">
                                            {PLATFORM_LABELS[
                                                discovery.site.platform
                                            ] ?? discovery.site.platform}
                                        </ToneBadge>
                                    )}
                                {discovery.site?.needs_js && (
                                    <ToneBadge tone="info">
                                        JavaScript site
                                    </ToneBadge>
                                )}
                            </div>
                            <p
                                suppressHydrationWarning
                                className="text-sm text-muted-foreground"
                            >
                                {discovery.paused
                                    ? 'Paused. Resume to continue discovery.'
                                    : running
                                      ? `Started ${timeAgo(discovery.started_at ?? discovery.created_at)}. Routes appear below as each page is checked.`
                                      : discovery.status === 'done'
                                        ? `${formatCount(productRoutes.length)} of ${formatCount(routes.length)} checked ${routes.length === 1 ? 'route lists' : 'routes list'} products. High-score routes are ticked.`
                                        : `Finished ${timeAgo(discovery.finished_at)}.`}
                            </p>
                        </div>
                    </div>

                    {(running || discovery.paused) && (
                        <CollectionControls
                            id={discovery.id}
                            paused={discovery.paused}
                        />
                    )}
                    {!running && !discovery.paused && (
                        <Button
                            variant="outline"
                            onClick={() =>
                                router.post(
                                    DiscoveryController.rediscover.url(
                                        discovery.id,
                                    ),
                                )
                            }
                        >
                            <RefreshCw /> Discover again
                        </Button>
                    )}
                </header>

                {discovery.paused && (
                    <p
                        role="status"
                        className="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm"
                    >
                        Discovery is paused. Resume to continue within 30
                        seconds. Discovery checks restart; collected products
                        remain saved.
                    </p>
                )}

                {running && (
                    <DiscoveryProgress
                        discovery={discovery}
                        stalled={stalled}
                    />
                )}

                {discovery.status === 'done' && routes.length > 0 && (
                    <dl className="grid grid-cols-2 gap-3 md:grid-cols-4">
                        <Stat
                            label="Candidate pages"
                            value={formatCount(
                                Math.max(discovery.candidates, routes.length),
                            )}
                        />
                        <Stat
                            label="Pages checked"
                            value={formatCount(
                                discovery.probed || routes.length,
                            )}
                        />
                        <Stat
                            label="High-score routes"
                            value={formatCount(highRoutes.length)}
                            accent
                        />
                        <Stat
                            label="Est. products"
                            value={formatCount(totalEstimate)}
                        />
                    </dl>
                )}

                {discovery.status === 'failed' && (
                    <Alert variant="destructive">
                        <AlertTriangle />
                        <AlertTitle>
                            {discovery.site?.is_blocked
                                ? 'This site is blocked'
                                : 'Discovery failed'}
                        </AlertTitle>
                        <AlertDescription>
                            {discovery.error}
                            {discovery.site?.is_blocked &&
                                ' Catalogix does not get past logins, CAPTCHAs or bot challenges.'}
                        </AlertDescription>
                    </Alert>
                )}

                {routes.length === 0 &&
                    running &&
                    discovery.stage === 'probing' && <RouteSkeleton />}

                {routes.length === 0 && discovery.status === 'done' && (
                    <EmptyState
                        icon={AlertTriangle}
                        title="No product routes found"
                        description="The site may build its pages with JavaScript and the Python helper is not installed, or it lists no products publicly."
                    />
                )}

                {routes.length > 0 && (
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <div className="flex flex-wrap items-center gap-2 border-b bg-muted/40 px-3 py-2">
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    setSelected(
                                        new Set(productRoutes.map((r) => r.id)),
                                    )
                                }
                            >
                                <CheckSquare /> All with products
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() => setSelected(new Set())}
                            >
                                <Square /> None
                            </Button>
                            {running && (
                                <span className="ml-auto flex items-center gap-2 text-xs text-muted-foreground">
                                    <Spinner className="size-3" /> Still
                                    checking routes
                                </span>
                            )}
                        </div>

                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-10">
                                        <span className="sr-only">Extract</span>
                                    </TableHead>
                                    <TableHead>Route</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead className="text-right">
                                        Est. products
                                    </TableHead>
                                    <TableHead>Score</TableHead>
                                    <TableHead>Preview</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {routes.map((route) => (
                                    <TableRow
                                        key={route.id}
                                        data-state={
                                            selected.has(route.id)
                                                ? 'selected'
                                                : undefined
                                        }
                                        className={cn(
                                            'animate-in cursor-pointer duration-200 fade-in',
                                            route.score === 0 &&
                                                'text-muted-foreground',
                                        )}
                                        onClick={(event) => {
                                            if (
                                                !(
                                                    event.target as HTMLElement
                                                ).closest('a,button')
                                            ) {
                                                toggle(route.id);
                                            }
                                        }}
                                    >
                                        <TableCell>
                                            <Checkbox
                                                checked={selected.has(route.id)}
                                                onCheckedChange={() =>
                                                    toggle(route.id)
                                                }
                                                aria-label={`Extract ${route.path}`}
                                            />
                                        </TableCell>
                                        <TableCell className="max-w-72">
                                            <a
                                                href={route.url}
                                                target="_blank"
                                                rel="noreferrer"
                                                className="block truncate font-mono text-xs hover:underline"
                                                title={route.url}
                                            >
                                                {route.path}
                                            </a>
                                            <span className="text-xs text-muted-foreground">
                                                {SOURCE_LABELS[route.source]}
                                            </span>
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {route.category_name ?? (
                                                <span className="text-muted-foreground">
                                                    Not a product page
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-sm tabular-nums">
                                            {formatCount(route.est_products)}
                                        </TableCell>
                                        <TableCell>
                                            <ScoreBadge
                                                label={route.score_label}
                                                score={route.score}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex -space-x-2">
                                                {route.sample_images.length ===
                                                    0 && (
                                                    <span className="text-xs text-muted-foreground">
                                                        none
                                                    </span>
                                                )}
                                                {route.sample_images.map(
                                                    (src) => (
                                                        <img
                                                            key={src}
                                                            src={src}
                                                            alt=""
                                                            loading="lazy"
                                                            referrerPolicy="no-referrer"
                                                            className="size-9 rounded-md border-2 border-background bg-muted object-cover shadow-sm"
                                                        />
                                                    ),
                                                )}
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                {routes.length > 0 && (
                    <div className="sticky bottom-0 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur md:-mx-6 md:px-6">
                        <p className="text-sm text-muted-foreground">
                            <span className="font-medium text-foreground">
                                {selected.size}
                            </span>{' '}
                            {selected.size === 1 ? 'route' : 'routes'} ticked ·
                            about{' '}
                            <span className="font-mono text-foreground">
                                {formatCount(estimate)}
                            </span>{' '}
                            products
                        </p>
                        <Button
                            onClick={extract}
                            disabled={selected.size === 0 || submitting}
                        >
                            {submitting ? <Spinner /> : <Download />}
                            Extract {selected.size}{' '}
                            {selected.size === 1 ? 'route' : 'routes'}
                        </Button>
                    </div>
                )}
            </div>
        </>
    );
}

function Stat({
    label,
    value,
    accent = false,
}: {
    label: string;
    value: string;
    accent?: boolean;
}) {
    return (
        <div className="rounded-xl border bg-card px-4 py-3">
            <dt className="text-xs text-muted-foreground">{label}</dt>
            <dd
                className={cn(
                    'font-mono text-xl font-semibold tabular-nums',
                    accent && 'text-success',
                )}
            >
                {value}
            </dd>
        </div>
    );
}

function RouteSkeleton() {
    return (
        <div
            className="space-y-2 rounded-xl border p-4"
            aria-label="Loading routes"
        >
            {Array.from({ length: 6 }).map((_, index) => (
                <div key={index} className="flex items-center gap-4">
                    <Skeleton className="size-4" />
                    <Skeleton className="h-4 flex-1" />
                    <Skeleton className="h-4 w-24" />
                    <Skeleton className="h-4 w-12" />
                    <Skeleton className="size-9" />
                </div>
            ))}
        </div>
    );
}

DiscoverShow.layout = {
    breadcrumbs: [
        { title: 'Websites', href: discover() },
        { title: 'Routes', href: '#' },
    ],
};
