import { Head, Link, router } from '@inertiajs/react';
import { ArrowRight, History, RefreshCw, Trash2 } from 'lucide-react';
import ScrapeJobController from '@/actions/App/Http/Controllers/ScrapeJobController';
import { EmptyState } from '@/components/empty-state';
import { PaginationLinks } from '@/components/pagination-links';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
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
import { discover } from '@/routes';
import { index as jobsIndex, show } from '@/routes/jobs';
import type { Paginated, ScrapeJob } from '@/types/catalogix';

/*
 * Design brief
 * User: returning user looking for an earlier extraction.
 * Main task: reopen, re-run or clean up past jobs.
 * Key information: site, date, routes, products, images, status.
 * Primary action: open a job.
 * States: empty, list, paginated.
 */
export default function JobsIndex({ jobs }: { jobs: Paginated<ScrapeJob> }) {
    useLivePoll(
        jobs.data.some(
            (job) => job.status === 'queued' || job.status === 'running',
        ),
        ['jobs'],
        3000,
    );

    return (
        <>
            <Head title="History" />

            <div className="flex flex-col gap-5 px-4 py-6">
                <header>
                    <h1 className="text-xl font-semibold">History</h1>
                    <p className="text-sm text-muted-foreground">
                        Every extraction you ran. Re-running updates the same
                        products instead of duplicating them.
                    </p>
                </header>

                {jobs.data.length === 0 ? (
                    <EmptyState
                        icon={History}
                        title="No extractions yet"
                        description="Discover a domain, tick its routes and extract them."
                        action={
                            <Button asChild>
                                <Link href={discover()}>Discover a site</Link>
                            </Button>
                        }
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Site</TableHead>
                                    <TableHead>Started</TableHead>
                                    <TableHead className="text-right">
                                        Routes
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Products
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Images
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {jobs.data.map((job) => (
                                    <TableRow key={job.id}>
                                        <TableCell>
                                            <Link
                                                href={show(job.id)}
                                                className="font-mono text-sm hover:underline"
                                            >
                                                {job.domain ?? `Job #${job.id}`}
                                            </Link>
                                        </TableCell>
                                        <TableCell
                                            suppressHydrationWarning
                                            className="text-sm text-muted-foreground"
                                        >
                                            {timeAgo(job.created_at)}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-sm tabular-nums">
                                            {job.total_routes}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-sm tabular-nums">
                                            {formatCount(job.total_products)}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-sm tabular-nums">
                                            {formatCount(job.total_images)}
                                        </TableCell>
                                        <TableCell>
                                            <StatusBadge status={job.status} />
                                        </TableCell>
                                        <TableCell>
                                            <div className="flex justify-end gap-1">
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label="Re-run"
                                                    disabled={
                                                        job.status ===
                                                            'running' ||
                                                        job.status === 'queued'
                                                    }
                                                    onClick={() =>
                                                        router.post(
                                                            ScrapeJobController.rerun.url(
                                                                job.id,
                                                            ),
                                                        )
                                                    }
                                                >
                                                    <RefreshCw />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    aria-label="Delete"
                                                    onClick={() =>
                                                        confirm(
                                                            'Delete this job? Products stay in the catalogue.',
                                                        ) &&
                                                        router.delete(
                                                            ScrapeJobController.destroy.url(
                                                                job.id,
                                                            ),
                                                            {
                                                                preserveScroll: true,
                                                            },
                                                        )
                                                    }
                                                >
                                                    <Trash2 />
                                                </Button>
                                                <Button
                                                    variant="ghost"
                                                    size="icon"
                                                    asChild
                                                    aria-label="Open"
                                                >
                                                    <Link href={show(job.id)}>
                                                        <ArrowRight />
                                                    </Link>
                                                </Button>
                                            </div>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <PaginationLinks page={jobs} />
            </div>
        </>
    );
}

JobsIndex.layout = {
    breadcrumbs: [{ title: 'History', href: jobsIndex() }],
};
