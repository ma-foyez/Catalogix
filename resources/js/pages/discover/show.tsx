import { Head, router } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import {
    AlertTriangle,
    CheckSquare,
    Download,
    RefreshCw,
    Square,
} from 'lucide-react';
import { useMemo, useState } from 'react';
import DiscoveryController from '@/actions/App/Http/Controllers/DiscoveryController';
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
import { formatCount } from '@/lib/format';
import { discover } from '@/routes';
import type { DiscoveredRoute, Discovery } from '@/types/catalogix';

const SOURCE_LABELS: Record<DiscoveredRoute['source'], string> = {
    platform_api: 'Catalogue',
    sitemap: 'Sitemap',
    menu: 'Menu',
    pattern: 'URL pattern',
};

/*
 * Design brief
 * User: the person who just typed a domain.
 * Main task: review suggested routes and tick the ones to extract.
 * Key information: route path, category, estimated products, confidence score, three sample thumbnails.
 * Primary action: "Extract N routes".
 * States: discovering (skeleton rows filling in live), done, failed, blocked, nothing found.
 */
export default function DiscoverShow({
    discovery: start,
}: {
    discovery: Discovery;
}) {
    const [discovery, setDiscovery] = useState<Discovery>(start);
    const [selected, setSelected] = useState<Set<number>>(
        () =>
            new Set(
                (start.routes ?? [])
                    .filter((r) => r.is_selected)
                    .map((r) => r.id),
            ),
    );
    const [submitting, setSubmitting] = useState(false);

    useEcho<Discovery>(
        `discovery.${start.id}`,
        '.routes.discovered',
        (payload) => {
            setDiscovery(payload);

            // Pre-tick newly found high-score routes, keep the user's own choices for the rest.
            setSelected((current) => {
                const next = new Set(current);
                const known = new Set(
                    (discovery.routes ?? []).map((r) => r.id),
                );

                (payload.routes ?? []).forEach((route) => {
                    if (!known.has(route.id) && route.is_selected) {
                        next.add(route.id);
                    }
                });

                return next;
            });
        },
    );

    const routes = discovery.routes ?? [];
    const productRoutes = useMemo(
        () => routes.filter((route) => route.score > 0),
        [routes],
    );
    const running =
        discovery.status === 'queued' || discovery.status === 'running';
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

            <div className="flex flex-col gap-6 px-4 py-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div className="space-y-1">
                        <div className="flex flex-wrap items-center gap-2">
                            <h1 className="font-mono text-xl font-semibold">
                                {discovery.domain}
                            </h1>
                            <StatusBadge
                                status={discovery.status}
                                label={running ? 'Discovering' : undefined}
                            />
                            {discovery.site?.platform &&
                                discovery.site.platform !== 'unknown' && (
                                    <ToneBadge tone="neutral">
                                        {discovery.site.platform ===
                                        'woocommerce'
                                            ? 'WooCommerce'
                                            : discovery.site.platform ===
                                                'shopify'
                                              ? 'Shopify'
                                              : 'Custom site'}
                                    </ToneBadge>
                                )}
                            {discovery.site?.needs_js && (
                                <ToneBadge tone="info">
                                    JavaScript site
                                </ToneBadge>
                            )}
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {running
                                ? 'Reading the sitemap, catalogue and menu, then checking each page for products…'
                                : `${formatCount(productRoutes.length)} of ${formatCount(routes.length)} checked ${routes.length === 1 ? 'route lists' : 'routes list'} products. High-score routes are ticked.`}
                        </p>
                    </div>

                    <div className="flex flex-wrap gap-2">
                        {!running && (
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
                    </div>
                </header>

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

                {routes.length === 0 && running && <RouteSkeleton />}

                {routes.length === 0 &&
                    !running &&
                    discovery.status === 'done' && (
                        <EmptyState
                            icon={AlertTriangle}
                            title="No product routes found"
                            description="The site may build its pages with JavaScript and the Python helper is not installed, or it lists no products publicly."
                        />
                    )}

                {routes.length > 0 && (
                    <div className="overflow-hidden rounded-xl border">
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
                                        className="animate-in duration-150 fade-in"
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
                                            {route.est_products > 0
                                                ? formatCount(
                                                      route.est_products,
                                                  )
                                                : '0'}
                                        </TableCell>
                                        <TableCell>
                                            <ScoreBadge
                                                label={route.score_label}
                                                score={route.score}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex gap-1">
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
                                                            className="size-9 rounded border bg-muted object-cover"
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
                    <div className="sticky bottom-0 -mx-4 flex flex-wrap items-center justify-between gap-3 border-t bg-background/95 px-4 py-3 backdrop-blur">
                        <p className="text-sm text-muted-foreground">
                            <span className="font-medium text-foreground">
                                {selected.size}
                            </span>{' '}
                            {selected.size === 1 ? 'route' : 'routes'} ticked ·
                            about{' '}
                            <span className="font-mono">
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
        { title: 'Discover', href: discover() },
        { title: 'Routes', href: '#' },
    ],
};
