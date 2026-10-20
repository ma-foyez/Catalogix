import { Head, Link } from '@inertiajs/react';
import { Globe, Wand2 } from 'lucide-react';
import { EmptyState } from '@/components/empty-state';
import { PaginationLinks } from '@/components/pagination-links';
import { ToneBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { formatCount, timeAgo } from '@/lib/format';
import { index as sitesIndex } from '@/routes/sites';
import { edit as recipeEdit } from '@/routes/sites/recipe';
import type { Paginated } from '@/types/catalogix';

type SiteRow = {
    id: number;
    domain: string;
    platform: string;
    strategy: string | null;
    needs_js: boolean;
    has_recipe: boolean;
    recipe_valid: boolean;
    is_blocked: boolean;
    blocked_reason: string | null;
    products_count: number;
    last_success_at: string | null;
};

/*
 * Design brief
 * User: admin keeping extraction healthy.
 * Main task: spot sites whose recipe broke or that are blocked, and fix recipes.
 * Key information: platform, winning strategy, recipe health, blocked reason, product count, last success.
 * Primary action: "Recipe picker".
 * States: empty, healthy, recipe flagged, blocked.
 */
export default function SitesIndex({ sites }: { sites: Paginated<SiteRow> }) {
    return (
        <>
            <Head title="Sites & recipes" />

            <div className="flex flex-col gap-5 px-4 py-6">
                <header>
                    <h1 className="text-xl font-semibold">Sites & recipes</h1>
                    <p className="text-sm text-muted-foreground">
                        What worked on each site. A flagged recipe stopped
                        finding products — re-pick it in the visual recipe
                        picker (2–5 minutes, once per site).
                    </p>
                </header>

                {sites.data.length === 0 ? (
                    <EmptyState
                        icon={Globe}
                        title="No sites yet"
                        description="Sites appear here after their first discovery."
                    />
                ) : (
                    <div className="overflow-hidden rounded-xl border">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Domain</TableHead>
                                    <TableHead>Platform</TableHead>
                                    <TableHead>Winning strategy</TableHead>
                                    <TableHead>Recipe</TableHead>
                                    <TableHead className="text-right">
                                        Products
                                    </TableHead>
                                    <TableHead>Last success</TableHead>
                                    <TableHead className="text-right">
                                        <span className="sr-only">Actions</span>
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {sites.data.map((site) => (
                                    <TableRow key={site.id}>
                                        <TableCell>
                                            <p className="font-mono text-sm">
                                                {site.domain}
                                            </p>
                                            {site.is_blocked && (
                                                <p className="text-xs text-warning">
                                                    {site.blocked_reason ??
                                                        'Blocked'}
                                                </p>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {site.platform}
                                            {site.needs_js && (
                                                <ToneBadge
                                                    tone="info"
                                                    className="ml-2"
                                                >
                                                    JS
                                                </ToneBadge>
                                            )}
                                        </TableCell>
                                        <TableCell className="text-sm">
                                            {site.strategy ?? '—'}
                                        </TableCell>
                                        <TableCell>
                                            {!site.has_recipe && (
                                                <span className="text-xs text-muted-foreground">
                                                    None
                                                </span>
                                            )}
                                            {site.has_recipe &&
                                                site.recipe_valid && (
                                                    <ToneBadge tone="success">
                                                        Working
                                                    </ToneBadge>
                                                )}
                                            {site.has_recipe &&
                                                !site.recipe_valid && (
                                                    <ToneBadge tone="danger">
                                                        Flagged
                                                    </ToneBadge>
                                                )}
                                        </TableCell>
                                        <TableCell className="text-right font-mono text-sm tabular-nums">
                                            {formatCount(site.products_count)}
                                        </TableCell>
                                        <TableCell className="text-sm text-muted-foreground">
                                            {timeAgo(site.last_success_at)}
                                        </TableCell>
                                        <TableCell className="text-right">
                                            <Button
                                                asChild
                                                size="sm"
                                                variant={
                                                    site.has_recipe &&
                                                    !site.recipe_valid
                                                        ? 'default'
                                                        : 'outline'
                                                }
                                            >
                                                <Link
                                                    href={recipeEdit(site.id)}
                                                >
                                                    <Wand2 /> Recipe picker
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </div>
                )}

                <PaginationLinks page={sites} />
            </div>
        </>
    );
}

SitesIndex.layout = {
    breadcrumbs: [{ title: 'Sites & recipes', href: sitesIndex() }],
};
