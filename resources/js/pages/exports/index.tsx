import { Head, router, usePage } from '@inertiajs/react';
import { useEcho } from '@laravel/echo-react';
import { Download, FileDown, Trash2 } from 'lucide-react';
import ExportController from '@/actions/App/Http/Controllers/ExportController';
import { EmptyState } from '@/components/empty-state';
import { PaginationLinks } from '@/components/pagination-links';
import { StatusBadge, ToneBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { useThrottledReload } from '@/hooks/use-throttled-reload';
import { formatBytes, formatCount, timeAgo } from '@/lib/format';
import { index as exportsIndex } from '@/routes/exports';
import type { Auth } from '@/types';
import type { ExportFile, Paginated } from '@/types/catalogix';

const SCOPE_LABELS = {
    route: 'One route',
    site: 'One site',
    all: 'All sites',
} as const;

/*
 * Design brief
 * User: someone collecting the files they exported.
 * Main task: download a ready file before its link expires.
 * Key information: format, scope, rows, size, status, expiry.
 * Primary action: "Download".
 * States: empty, building (live), ready, failed, expired.
 */
export default function ExportsIndex({
    exports,
}: {
    exports: Paginated<ExportFile>;
}) {
    const { auth } = usePage<{ auth: Auth }>().props;
    const reload = useThrottledReload(['exports'], 300);

    useEcho(
        `App.Models.User.${auth.user.id}`,
        '.export.ready',
        () => reload(),
        [reload],
    );

    return (
        <>
            <Head title="Exports" />

            <div className="flex flex-col gap-5 px-4 py-6">
                <header>
                    <h1 className="text-xl font-semibold">Exports</h1>
                    <p className="text-sm text-muted-foreground">
                        Download links are signed and work for 24 hours; the
                        file is removed after that.
                    </p>
                </header>

                {exports.data.length === 0 ? (
                    <EmptyState
                        icon={FileDown}
                        title="No exports yet"
                        description="Open a job and choose Export to build a CSV, an Excel file with pictures, or an images ZIP."
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>File</TableHead>
                                    <TableHead>Scope</TableHead>
                                    <TableHead className="text-right">
                                        Rows
                                    </TableHead>
                                    <TableHead className="text-right">
                                        Size
                                    </TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead className="text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {exports.data.map((file) => {
                                    const expired =
                                        file.status === 'ready' &&
                                        file.download_url === null;

                                    return (
                                        <TableRow key={file.id}>
                                            <TableCell className="max-w-80">
                                                <p
                                                    className="truncate font-mono text-xs"
                                                    title={
                                                        file.file_name ??
                                                        undefined
                                                    }
                                                >
                                                    {file.file_name ??
                                                        `${file.format_label} export`}
                                                </p>
                                                <p className="text-xs text-muted-foreground">
                                                    {file.format_label} ·{' '}
                                                    {timeAgo(file.created_at)}
                                                </p>
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                {SCOPE_LABELS[file.scope.type]}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-sm tabular-nums">
                                                {formatCount(file.row_count)}
                                            </TableCell>
                                            <TableCell className="text-right font-mono text-sm tabular-nums">
                                                {formatBytes(file.bytes)}
                                            </TableCell>
                                            <TableCell>
                                                {expired ? (
                                                    <ToneBadge tone="neutral">
                                                        Expired
                                                    </ToneBadge>
                                                ) : (
                                                    <StatusBadge
                                                        status={file.status}
                                                    />
                                                )}
                                                {file.status === 'failed' && (
                                                    <p
                                                        className="mt-1 max-w-64 truncate text-xs text-destructive"
                                                        title={
                                                            file.error ??
                                                            undefined
                                                        }
                                                    >
                                                        {file.error}
                                                    </p>
                                                )}
                                                {file.status === 'ready' &&
                                                    file.expires_at &&
                                                    !expired && (
                                                        <p className="mt-1 text-xs text-muted-foreground">
                                                            expires{' '}
                                                            {timeAgo(
                                                                file.expires_at,
                                                            )}
                                                        </p>
                                                    )}
                                            </TableCell>
                                            <TableCell>
                                                <div className="flex justify-end gap-1">
                                                    {file.download_url && (
                                                        <Button
                                                            asChild
                                                            size="sm"
                                                        >
                                                            <a
                                                                href={
                                                                    file.download_url
                                                                }
                                                            >
                                                                <Download />{' '}
                                                                Download
                                                            </a>
                                                        </Button>
                                                    )}
                                                    <Button
                                                        variant="ghost"
                                                        size="icon"
                                                        aria-label="Delete export"
                                                        onClick={() =>
                                                            router.delete(
                                                                ExportController.destroy.url(
                                                                    file.id,
                                                                ),
                                                                {
                                                                    preserveScroll: true,
                                                                },
                                                            )
                                                        }
                                                    >
                                                        <Trash2 />
                                                    </Button>
                                                </div>
                                            </TableCell>
                                        </TableRow>
                                    );
                                })}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <PaginationLinks page={exports} />
            </div>
        </>
    );
}

ExportsIndex.layout = {
    breadcrumbs: [{ title: 'Exports', href: exportsIndex() }],
};
