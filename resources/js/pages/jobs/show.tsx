import { Head, Link, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import {
    AlertTriangle,
    ArrowLeft,
    Clock,
    Download,
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
import { ProductDetails, StockBadge } from '@/components/product-details';
import { ProductThumb } from '@/components/product-thumb';
import { StatusBadge } from '@/components/status-badge';
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
import { useExcelDownload } from '@/hooks/use-excel-download';
import { useLivePoll } from '@/hooks/use-live-poll';
import { CollectionControls } from '@/components/collection-controls';
import { useThrottledReload } from '@/hooks/use-throttled-reload';
import { formatCount, formatTaka } from '@/lib/format';
import { cn } from '@/lib/utils';
import { discover } from '@/routes';
import { show } from '@/routes/jobs';
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
 * User: someone who collected a shop's products and now wants to look through them or take them away.
 * Main task: browse products with their pictures and download an Excel file.
 * Key information: picture, name, SKU/brand, category › subcategory, ৳ price and sale price with
 * discount, stock; full details of one product on click.
 * Primary action: "Download Excel" (with pictures, one click).
 * States: still collecting (live), ready, some sections failed or blocked, empty filter result.
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
    const [selected, setSelected] = useState<Product | null>(null);
    const reload = useThrottledReload(LIVE_PROPS);
    const excel = useExcelDownload();

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
    const running = job.status === 'queued' || job.status === 'running';
    const { queueStalled } = usePage().props;
    const stalled =
        job.status === 'queued' &&
        (queueStalled?.queues.includes('pages') ?? false);
    const crawling = routes.find((route) => route.status === 'crawling');
    const working = !job.paused && (running || imageStats.pending > 0);
    const imageTotal =
        imageStats.pending + imageStats.downloaded + imageStats.failed;
    const progress = running
        ? 5 + Math.round((routes.length ? finished / routes.length : 0) * 75)
        : imageTotal > 0
          ? 80 +
            Math.round(
                ((imageStats.downloaded + imageStats.failed) / imageTotal) * 20,
            )
          : 100;

    useLivePoll(working, LIVE_PROPS, 3000);

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
            <Head title={`Products · ${job.domain ?? 'Website'}`} />

            <div className="flex flex-col gap-5 px-4 py-6 md:px-6">
                <Link
                    href={discover()}
                    className="flex w-fit items-center gap-1 text-sm text-muted-foreground hover:text-foreground"
                >
                    <ArrowLeft className="size-4" /> All websites
                </Link>

                <header className="space-y-4">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div className="space-y-1">
                            <div className="flex flex-wrap items-center gap-2">
                                <h1 className="font-mono text-xl font-semibold">
                                    {job.domain ?? `Website #${job.id}`}
                                </h1>
                                {job.paused ? (
                                    <StatusBadge status="paused" />
                                ) : working ? (
                                    <StatusBadge
                                        status="running"
                                        label={
                                            running
                                                ? 'Collecting products'
                                                : 'Saving pictures'
                                        }
                                    />
                                ) : (
                                    <StatusBadge
                                        status={job.status}
                                        label={
                                            job.status === 'done'
                                                ? 'Ready'
                                                : undefined
                                        }
                                    />
                                )}
                            </div>
                            <p className="text-sm text-muted-foreground">
                                <span className="font-mono text-foreground">
                                    {formatCount(job.total_products)}
                                </span>{' '}
                                products ·{' '}
                                <span className="font-mono text-foreground">
                                    {formatCount(imageStats.downloaded)}
                                </span>{' '}
                                pictures saved
                                {job.max_products !== null && (
                                    <>
                                        {' '}
                                        · Limit: {formatCount(job.max_products)}
                                        {job.limit_reached ? ' (reached)' : ''}
                                    </>
                                )}
                                {imageStats.failed > 0 && (
                                    <>
                                        {' '}
                                        ·{' '}
                                        <span className="text-warning">
                                            {formatCount(imageStats.failed)}{' '}
                                            missing
                                        </span>
                                    </>
                                )}
                            </p>
                        </div>

                        <div className="flex flex-wrap gap-2">
                            {(working || job.paused) && (
                                <CollectionControls
                                    kind="jobs"
                                    id={job.id}
                                    paused={job.paused}
                                />
                            )}
                            {imageStats.failed > 0 && !working && (
                                <Button
                                    variant="ghost"
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
                                    <ImageOff /> Retry missing pictures
                                </Button>
                            )}
                            {!running && !job.paused && (
                                <Button
                                    variant="ghost"
                                    onClick={() =>
                                        router.post(
                                            ScrapeJobController.rerun.url(
                                                job.id,
                                            ),
                                        )
                                    }
                                >
                                    <RefreshCw /> Refresh
                                </Button>
                            )}
                            <ExportDialog
                                jobId={job.id}
                                domain={job.domain}
                                route={activeRoute}
                                totalProducts={job.total_products}
                            />
                            <Button
                                onClick={() => excel.download(job.id)}
                                disabled={
                                    job.total_products === 0 ||
                                    excel.preparing(job.id)
                                }
                            >
                                {excel.preparing(job.id) ? (
                                    <Spinner />
                                ) : (
                                    <Download />
                                )}
                                {excel.preparing(job.id)
                                    ? 'Preparing…'
                                    : 'Download Excel'}
                            </Button>
                        </div>
                    </div>

                    {working && (
                        <div className="space-y-1.5 rounded-xl border bg-muted/30 px-4 py-3">
                            <div className="flex flex-wrap items-center justify-between gap-2 text-sm">
                                <JobActivity
                                    running={running}
                                    stalled={stalled}
                                    crawling={crawling}
                                    pendingImages={imageStats.pending}
                                />
                                <span className="text-xs text-muted-foreground tabular-nums">
                                    {finished} of {routes.length} sections done
                                </span>
                            </div>
                            <div
                                className="h-1.5 overflow-hidden rounded-full bg-muted"
                                role="progressbar"
                                aria-valuenow={progress}
                                aria-valuemin={0}
                                aria-valuemax={100}
                                aria-label="Collection progress"
                            >
                                <div
                                    className="h-full rounded-full bg-linear-to-r from-primary to-fuchsia-500 transition-[width] duration-500"
                                    style={{
                                        width: `${Math.max(progress, 3)}%`,
                                    }}
                                />
                            </div>
                        </div>
                    )}
                </header>

                {job.paused && (
                    <p
                        role="status"
                        className="rounded-xl border border-warning/30 bg-warning/10 px-4 py-3 text-sm"
                    >
                        Collection is paused. A page or picture already being
                        processed may finish. Your products are saved; resume to
                        continue within 30 seconds.
                    </p>
                )}

                {problemRoutes.length > 0 && (
                    <Alert>
                        <AlertTriangle />
                        <AlertTitle>
                            {problemRoutes.length}{' '}
                            {problemRoutes.length === 1
                                ? 'section'
                                : 'sections'}{' '}
                            could not be read
                        </AlertTitle>
                        <AlertDescription>
                            <ul className="mt-1 space-y-1">
                                {problemRoutes.map((route) => (
                                    <li key={route.id} className="text-sm">
                                        <span className="font-medium">
                                            {route.category ??
                                                new URL(route.url).pathname}
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
                                            <Wand2 /> Teach Catalogix this
                                            layout
                                        </Link>
                                    </Button>
                                )}
                        </AlertDescription>
                    </Alert>
                )}

                {routes.length > 1 && (
                    <nav
                        className="-mx-4 flex gap-1 overflow-x-auto border-b px-4 md:-mx-6 md:px-6"
                        aria-label="Sections"
                    >
                        <SectionTab
                            active={filters.route === null}
                            onClick={() => applyFilters({ route: null })}
                            label="All"
                            count={job.total_products}
                        />
                        {routes.map((route) => (
                            <SectionTab
                                key={route.id}
                                active={filters.route === String(route.id)}
                                onClick={() =>
                                    applyFilters({ route: String(route.id) })
                                }
                                label={
                                    route.category ??
                                    new URL(route.url).pathname
                                }
                                count={route.products_found}
                                status={route.status}
                            />
                        ))}
                    </nav>
                )}

                <div className="flex flex-col gap-2 sm:flex-row sm:items-center">
                    <div className="relative flex-1">
                        <Search className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                        <Input
                            value={search}
                            onChange={(event) => setSearch(event.target.value)}
                            placeholder="Search by name, SKU or brand"
                            className="pl-9"
                            aria-label="Search products"
                        />
                    </div>
                    {categories.length > 1 && (
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
                                <SelectItem value="all">
                                    All categories
                                </SelectItem>
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
                    )}
                    {(imageStats.failed > 0 || filters.image === 'failed') && (
                        <Button
                            variant={
                                filters.image === 'failed'
                                    ? 'default'
                                    : 'outline'
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
                            <ImageOff /> Missing pictures
                        </Button>
                    )}
                </div>

                {rows.length === 0 && running && <ProductSkeleton />}

                {rows.length === 0 && !running && (
                    <EmptyState
                        icon={PackageSearch}
                        title={
                            filters.search || filters.category || filters.image
                                ? 'No products match'
                                : 'No products were found'
                        }
                        description={
                            filters.search || filters.category || filters.image
                                ? 'Clear the search or filter to see more.'
                                : 'The pages were checked but no products could be read. See the note above.'
                        }
                    />
                )}

                {rows.length > 0 && (
                    <div className="overflow-hidden rounded-xl border bg-card">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead className="w-20">
                                        Picture
                                    </TableHead>
                                    <TableHead>Product</TableHead>
                                    <TableHead>Category</TableHead>
                                    <TableHead className="text-right">
                                        Price
                                    </TableHead>
                                    <TableHead>Stock</TableHead>
                                    <TableHead className="w-10">
                                        <span className="sr-only">
                                            On website
                                        </span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {rows.map((product) => (
                                    <TableRow
                                        key={product.id}
                                        className="animate-in cursor-pointer duration-150 fade-in"
                                        onClick={(event) => {
                                            if (
                                                !(
                                                    event.target as HTMLElement
                                                ).closest('a,button')
                                            ) {
                                                setSelected(product);
                                            }
                                        }}
                                    >
                                        <TableCell>
                                            <ProductThumb
                                                image={product.image}
                                                name={product.name}
                                                className="size-16"
                                            />
                                        </TableCell>
                                        <TableCell className="max-w-96 min-w-56">
                                            <button
                                                type="button"
                                                onClick={() =>
                                                    setSelected(product)
                                                }
                                                className="line-clamp-2 text-left text-sm font-medium hover:underline focus-visible:underline focus-visible:outline-none"
                                            >
                                                {product.name}
                                            </button>
                                            {(product.brand || product.sku) && (
                                                <p className="truncate font-mono text-xs text-muted-foreground">
                                                    {[
                                                        product.brand,
                                                        product.sku &&
                                                            `SKU ${product.sku}`,
                                                    ]
                                                        .filter(Boolean)
                                                        .join(' · ')}
                                                </p>
                                            )}
                                            {product.description && (
                                                <p className="line-clamp-1 text-xs text-muted-foreground">
                                                    {product.description}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {product.category ?? '—'}
                                            {product.subcategory && (
                                                <span className="block text-xs text-muted-foreground">
                                                    › {product.subcategory}
                                                </span>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-sm whitespace-nowrap tabular-nums">
                                            {product.sale_price ? (
                                                <>
                                                    <span className="block font-medium text-success">
                                                        {formatTaka(
                                                            product.sale_price,
                                                        )}
                                                    </span>
                                                    <span className="text-xs text-muted-foreground line-through">
                                                        {formatTaka(
                                                            product.price,
                                                        )}
                                                    </span>
                                                    {product.discount_percent ? (
                                                        <span className="ml-1 rounded bg-success/10 px-1 text-[10px] font-semibold text-success">
                                                            −
                                                            {
                                                                product.discount_percent
                                                            }
                                                            %
                                                        </span>
                                                    ) : null}
                                                </>
                                            ) : (
                                                formatTaka(product.price)
                                            )}
                                        </TableCell>
                                        <TableCell>
                                            <StockBadge
                                                inStock={product.in_stock}
                                            />
                                        </TableCell>
                                        <TableCell>
                                            {product.product_url && (
                                                <a
                                                    href={product.product_url}
                                                    target="_blank"
                                                    rel="noreferrer"
                                                    className="inline-flex rounded p-1 text-muted-foreground hover:text-foreground focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                                    aria-label={`Open ${product.name} on the website`}
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
                        size="sm"
                        className="text-muted-foreground hover:text-destructive"
                        onClick={() => {
                            if (
                                confirm(
                                    'Delete this collection? The products stay saved for this website.',
                                )
                            ) {
                                router.delete(
                                    ScrapeJobController.destroy.url(job.id),
                                );
                            }
                        }}
                    >
                        <Trash2 /> Delete this collection
                    </Button>
                </div>
            </div>

            <ProductDetails
                product={selected}
                onOpenChange={(open) => !open && setSelected(null)}
            />
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
                <Clock className="size-4" /> Waiting for the background worker
            </span>
        );
    }

    if (crawling) {
        return (
            <span className="flex min-w-0 items-center gap-1.5 text-primary">
                <Spinner className="size-4 shrink-0" />
                <span className="font-medium">Collecting</span>
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
                <Spinner className="size-4" />
                <span className="font-medium">Collecting</span>
                <span className="text-muted-foreground">
                    next page in a moment — Catalogix pauses between pages to be
                    polite…
                </span>
            </span>
        );
    }

    return (
        <span className="flex items-center gap-1.5 text-primary">
            <Spinner className="size-4" />
            <span className="font-medium">Saving pictures</span>
            <span className="text-muted-foreground">
                {formatCount(pendingImages)} left
            </span>
        </span>
    );
}

function SectionTab({
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
            {status && status !== 'done' && (
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
                    <Skeleton className="size-16" />
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
        { title: 'Websites', href: discover() },
        { title: 'Products', href: '#' },
    ],
};
