import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import {
    AlertTriangle,
    Clock,
    ExternalLink,
    ImageOff,
    PackageSearch,
    RefreshCw,
    Search,
    Trash2,
    Wand2,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import ScrapeJobController from '@/actions/App/Http/Controllers/ScrapeJobController';
import { EmptyState } from '@/components/empty-state';
import { ExportDialog } from '@/components/export-dialog';
import { PaginationLinks } from '@/components/pagination-links';
import { ProductThumb } from '@/components/product-thumb';
import { StatusBadge, ToneBadge } from '@/components/status-badge';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
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
import { useThrottledReload } from '@/hooks/use-throttled-reload';
import { formatCount, formatTaka } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as jobsIndex, show } from '@/routes/jobs';
import { edit as recipeEdit } from '@/routes/sites/recipe';
import type {
    Paginated,
    Product,
    ProductImage,
    ScrapeJob,
    ScrapeTarget,
} from '@/types/catalogix';

type Filters = {
    route: string | null;
    category: string | null;
    search: string;
    image: string | null;
};

type Props = {
    job: ScrapeJob;
    products: Paginated<Product>;
    filters: Filters;
    categories: { id: number; name: string; count: number }[];
    imageStats: { pending: number; downloaded: number; failed: number };
    isAdmin: boolean;
};

const LIVE_PROPS = ['job', 'products', 'categories', 'imageStats'];

/*
 * Design brief
 * User: someone watching an extraction run, then filtering and exporting.
 * Main task: see products arrive per route and export the right slice.
 * Key information: progress per route, product rows with local thumbnail, ৳ price and sale price, category, stock.
 * Primary action: "Export".
 * States: queued/crawling (skeleton rows, live rows), done, partially failed, blocked, empty filter result.
 */
export default function JobShow({
    job,
    products,
    filters,
    categories,
    imageStats,
    isAdmin,
}: Props) {
    const [rows, setRows] = useState<Product[]>(products.data);
    const [search, setSearch] = useState(filters.search);
    const reload = useThrottledReload(LIVE_PROPS);

    useEffect(() => setRows(products.data), [products]);

    useEcho(
        `scrape-job.${job.id}`,
        ['.products.found', '.scrape.progress'],
        () => reload(),
        [reload],
    );

    useEcho<{ productId: number; image: ProductImage }>(
        `scrape-job.${job.id}`,
        '.image.downloaded',
        ({ productId, image }) => {
            setRows((current) =>
                current.map((product) =>
                    product.id === productId ? { ...product, image } : product,
                ),
            );
        },
    );

    const routes = job.routes ?? [];
    const finished = routes.filter((route) =>
        ['done', 'blocked', 'failed'].includes(route.status),
    ).length;
    const progress =
        routes.length > 0 ? Math.round((finished / routes.length) * 100) : 0;
    const running = job.status === 'queued' || job.status === 'running';
    const { queueStalled } = usePage().props;
    const stalled =
        job.status === 'queued' &&
        (queueStalled?.queues.includes('pages') ?? false);
    const crawling = routes.find((route) => route.status === 'crawling');

    useLivePoll(running || imageStats.pending > 0, LIVE_PROPS, 3000);
    const activeRoute =
        routes.find((route) => String(route.id) === filters.route) ?? null;
    const problemRoutes = routes.filter(
        (route) => route.status === 'failed' || route.status === 'blocked',
    );

    const applyFilters = (changes: Partial<Filters>) => {
        const next = { ...filters, search, ...changes };

        router.get(
            show.url(job.id),
            Object.fromEntries(
                Object.entries(next).filter(
                    ([, value]) => value !== null && value !== '',
                ),
            ),
            {
                only: ['products', 'filters'],
                preserveState: true,
                preserveScroll: true,
                replace: true,
            },
        );
    };

    useEffect(() => {
        if (search === filters.search) {
            return;
        }

        const timer = setTimeout(() => applyFilters({ search }), 350);

        return () => clearTimeout(timer);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [search]);

    return (
        <>
            <Head title={`Products · ${job.domain ?? 'Job #' + job.id}`} />

            <div className="flex flex-col gap-5 px-4 py-6">
                <header className="space-y-4">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="font-mono text-xl font-semibold">
                                    {job.domain ?? `Job #${job.id}`}
                                </h1>
                                <StatusBadge status={job.status} />
                            </div>
                            <p className="text-sm text-muted-foreground">
                                <span className="font-mono text-foreground">
                                    {formatCount(job.total_products)}
                                </span>{' '}
                                products ·{' '}
                                <span className="font-mono text-foreground">
                                    {formatCount(imageStats.downloaded)}
                                </span>{' '}
                                images saved
                                {imageStats.pending > 0 && (
                                    <>
                                        {' '}
                                        · {formatCount(imageStats.pending)}{' '}
                                        downloading
                                    </>
                                )}
                                {imageStats.failed > 0 && (
                                    <>
                                        {' '}
                                        ·{' '}
                                        <span className="text-destructive">
                                            {formatCount(imageStats.failed)}{' '}
                                            failed
                                        </span>
                                    </>
                                )}
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {imageStats.failed > 0 && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            ScrapeJobController.retryImages.url(
                                                job.id,
                                            ),
                                            {},
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    <ImageOff /> Retry {imageStats.failed}{' '}
                                    images
                                </Button>
                            )}
                            {!running && (
                                <Button
                                    variant="outline"
                                    onClick={() =>
                                        router.post(
                                            ScrapeJobController.rerun.url(
                                                job.id,
                                            ),
                                        )
                                    }
                                >
                                    <RefreshCw /> Re-run
                                </Button>
                            )}
                            <ExportDialog
                                jobId={job.id}
                                domain={job.domain}
                                route={activeRoute}
                                totalProducts={job.total_products}
                            />
                        </div>
                    </div>

                    <div className="space-y-1.5">
                        <div
                            className="h-1.5 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            aria-valuenow={progress}
                            aria-valuemin={0}
                            aria-valuemax={100}
                            aria-label="Routes finished"
                        >
                            <div
                                className={cn(
                                    'h-full rounded-full bg-primary transition-[width] duration-200',
                                    running && 'animate-pulse',
                                )}
                                style={{
                                    width: `${Math.max(progress, running ? 4 : 0)}%`,
                                }}
                            />
                        </div>
                        <div className="flex flex-wrap items-center justify-between gap-2 text-xs text-muted-foreground">
                            <JobActivity
                                running={running}
                                stalled={stalled}
                                crawling={crawling}
                                pendingImages={imageStats.pending}
                            />
                            <span className="tabular-nums">
                                {finished} of {routes.length} routes finished
                            </span>
                        </div>
                    </div>
                </header>

                {problemRoutes.length > 0 && (
                    <Alert
                        variant={
                            problemRoutes.every(
                                (route) => route.status === 'blocked',
                            )
                                ? 'default'
                                : 'destructive'
                        }
                    >
                        <AlertTriangle />
                        <AlertTitle>
                            {problemRoutes.length}{' '}
                            {problemRoutes.length === 1 ? 'route' : 'routes'}{' '}
                            could not be extracted
                        </AlertTitle>
                        <AlertDescription>
                            <ul className="mt-1 space-y-1">
                                {problemRoutes.map((route) => (
                                    <li key={route.id} className="text-sm">
                                        <span className="font-mono">
                                            {new URL(route.url).pathname}
                                        </span>{' '}
                                        — {route.error}
                                    </li>
                                ))}
                            </ul>
                            {isAdmin &&
                                job.site_id &&
                                problemRoutes.some(
                                    (route) => route.status === 'failed',
                                ) && (
                                    <Button
                                        asChild
                                        size="sm"
                                        variant="outline"
                                        className="mt-2"
                                    >
                                        <Link
                                            href={recipeEdit.url(job.site_id, {
                                                query: {
                                                    url: problemRoutes.find(
                                                        (r) =>
                                                            r.status ===
                                                            'failed',
                                                    )?.url,
                                                },
                                            })}
                                        >
                                            <Wand2 /> Open visual recipe picker
                                        </Link>
                                    </Button>
                                )}
                        </AlertDescription>
                    </Alert>
                )}

                <nav
                    className="-mx-4 flex gap-1 overflow-x-auto border-b px-4"
                    aria-label="Routes"
                >
                    <RouteTab
                        active={filters.route === null}
                        onClick={() => applyFilters({ route: null })}
                        label="All"
                        count={job.total_products}
                    />
                    {routes.map((route) => (
                        <RouteTab
                            key={route.id}
                            active={filters.route === String(route.id)}
                            onClick={() =>
                                applyFilters({ route: String(route.id) })
                            }
                            label={
                                route.category ?? new URL(route.url).pathname
                            }
                            count={route.products_found}
                            status={route.status}
                        />
                    ))}
                </nav>

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search name, SKU or brand"
                            className="pl-9"
                            aria-label="Search products"
                        />
                    </div>
                    <Select
                        value={filters.category ?? 'all'}
                        onValueChange={(value) =>
                            applyFilters({
                                category: value === 'all' ? null : value,
                            })
                        }
                    >
                        <SelectTrigger
                            className="sm:w-56"
                            aria-label="Category"
                        >
                            <SelectValue placeholder="All categories" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="all">All categories</SelectItem>
                            {categories.map((category) => (
                                <SelectItem
                                    key={category.id}
                                    value={String(category.id)}
                                >
                                    {category.name} ({category.count})
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <Button
                        variant={
                            filters.image === 'failed' ? 'default' : 'outline'
                        }
                        onClick={() =>
                            applyFilters({
                                image:
                                    filters.image === 'failed'
                                        ? null
                                        : 'failed',
                            })
                        }
                        aria-pressed={filters.image === 'failed'}
                    >
                        <ImageOff /> Failed images
                    </Button>
                </div>

                {rows.length === 0 && running && <ProductSkeleton />}

                {rows.length === 0 && !running && (
                    <EmptyState
                        icon={PackageSearch}
                        title={
                            filters.search || filters.category || filters.image
                                ? 'No products match these filters'
                                : 'No products were found'
                        }
                        description={
                            filters.search || filters.category || filters.image
                                ? 'Clear a filter to see more.'
                                : 'Every route finished without products. See the notes above.'
                        }
                    />
                )}

                {rows.length > 0 && (
                    <div className="overflow-hidden rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-16">
                                        Image
                                    </TableHead>
                                    <TableHead>Name</TableHead>
                                    <TableHead className="text-right">
                                        Price
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Sale price
                                    </TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead>Stock</TableHead>
                                    <TableHead className="w-10">
                                        <span className="sr-only">Link</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((product) => (
                                    <TableRow
                                        key={product.id}
                                        className="animate-in duration-150 fade-in"
                                    >
                                        <TableCell>
                                            <ProductThumb
                                                image={product.image}
                                                name={product.name}
                                            />
                                        </TableCell>
                                        <TableCell className="max-w-96">
                                            <p className="line-clamp-2 text-sm font-medium">
                                                {product.name}
                                            </p>
                                            {(product.brand || product.sku) && (
                                                <p className="truncate font-mono text-xs text-muted-foreground">
                                                    {[
                                                        product.brand,
                                                        product.sku,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell
                                            className={cn(
                                                'text-right font-mono text-sm whitespace-nowrap tabular-nums',
                                                product.sale_price &&
                                                    'text-muted-foreground line-through',
                                            )}
                                        >
                                            {formatTaka(product.price)}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-sm font-medium whitespace-nowrap text-success tabular-nums">
                                            {product.sale_price
                                                ? formatTaka(product.sale_price)
                                                : ''}
                                        </TableCell>
                                        <TableCell className="text-sm whitespace-nowrap">
                                            {product.category ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {product.in_stock === true && (
                                                <ToneBadge tone="success">
                                                    In stock
                                                </ToneBadge>
                                            )}
                                            {product.in_stock === false && (
                                                <ToneBadge tone="danger">
                                                    Out of stock
                                                </ToneBadge>
                                            )}
                                            {product.in_stock === null && (
                                                <span className="text-xs text-muted-foreground">
                                                    —
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            {product.product_url && (
                                                <a
                                                    href={product.product_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex rounded p-1 text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    aria-label={`Open ${product.name} on the site`}
                                                >
                                                    <ExternalLink className="size-4" />
                                                </a>
                                            )}
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <PaginationLinks
                    page={products}
                    only={['products', 'filters']}
                />

                <div className="flex justify-end">
                    <Button
                        variant="ghost"
                        className="text-destructive hover:text-destructive"
                        onClick={() => {
                            if (
                                confirm(
                                    'Delete this job? Products stay in the catalogue and in other jobs.',
                                )
                            ) {
                                router.delete(
                                    ScrapeJobController.destroy.url(job.id),
                                );
                            }
                        }}
                    >
                        <Trash2 /> Delete job
                    </Button>
                </div>
            </div>
        </>
    );
}

function JobActivity({
    running,
    stalled,
    crawling,
    pendingImages,
}: {
    running: boolean;
    stalled: boolean;
    crawling: ScrapeTarget | undefined;
    pendingImages: number;
}) {
    if (stalled) {
        return (
            <span className="flex items-center gap-1.5 text-warning">
                <Clock className="size-3" /> Waiting for a queue worker
            </span>
        );
    }

    if (crawling) {
        return (
            <span className="flex min-w-0 items-center gap-1.5 text-primary">
                <Spinner className="size-3 shrink-0" />
                <span className="font-medium">Extracting</span>
                <span className="truncate text-muted-foreground">
                    {crawling.category ?? new URL(crawling.url).pathname} · page{' '}
                    {crawling.pages_crawled + 1} ·{' '}
                    {formatCount(crawling.products_found)} products so far
                </span>
            </span>
        );
    }

    if (running) {
        return (
            <span className="flex items-center gap-1.5 text-primary">
                <Spinner className="size-3" />
                <span className="font-medium">Queued</span>
                <span className="text-muted-foreground">
                    next page starts after a short polite pause…
                </span>
            </span>
        );
    }

    if (pendingImages > 0) {
        return (
            <span className="flex items-center gap-1.5 text-primary">
                <Spinner className="size-3" />
                <span className="font-medium">Downloading images</span>
                <span className="text-muted-foreground">
                    {formatCount(pendingImages)} left
                </span>
            </span>
        );
    }

    return <span />;
}

function RouteTab({
    active,
    onClick,
    label,
    count,
    status,
}: {
    active: boolean;
    onClick: () => void;
    label: string;
    count: number;
    status?: ScrapeTarget['status'];
}) {
    const dot = {
        queued: 'bg-muted-foreground',
        crawling: 'bg-info animate-pulse',
        done: 'bg-success',
        blocked: 'bg-warning',
        failed: 'bg-destructive',
    } as const;

    return (
        <button
            type="button"
            onClick={onClick}
            aria-current={active ? 'page' : undefined}
            className={cn(
                '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-2 text-sm whitespace-nowrap transition-colors focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                active
                    ? 'border-primary font-medium text-foreground'
                    : 'border-transparent text-muted-foreground hover:text-foreground',
            )}
        >
            {status && (
                <span
                    aria-label={status}
                    className={cn('size-2 rounded-full', dot[status])}
                />
            )}
            {label}
            <span className="rounded bg-muted px-1.5 font-mono text-xs tabular-nums">
                {formatCount(count)}
            </span>
        </button>
    );
}

function ProductSkeleton() {
    return (
        <div
            className="space-y-3 rounded-xl border p-4"
            aria-label="Waiting for products"
        >
            {Array.from({ length: 6 }).map((_, index) => (
                <div key={index} className="flex items-center gap-4">
                    <Skeleton className="size-12" />
                    <Skeleton className="h-4 flex-1" />
                    <Skeleton className="h-4 w-20" />
                    <Skeleton className="h-4 w-24" />
                </div>
            ))}
        </div>
    );
}

JobShow.layout = {
    breadcrumbs: [
        { title: 'History', href: jobsIndex() },
        { title: 'Products', href: '#' },
    ],
};
