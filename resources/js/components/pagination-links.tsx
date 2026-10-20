import { Link } from '@inertiajs/react';
import { cn } from '@/lib/utils';
import type { Paginated } from '@/types/catalogix';

export function PaginationLinks<T>({
    page,
    only,
}: {
    page: Paginated<T>;
    only?: string[];
}) {
    if (page.meta.last_page <= 1) {
        return null;
    }

    return (
        <nav
            className="flex flex-wrap items-center justify-between gap-2 text-sm"
            aria-label="Pagination"
        >
            <p className="text-muted-foreground">
                {page.meta.from}–{page.meta.to} of {page.meta.total}
            </p>
            <div className="flex flex-wrap gap-1">
                {page.meta.links.map((link, index) =>
                    link.url ? (
                        <Link
                            key={index}
                            href={link.url}
                            only={only}
                            preserveScroll
                            preserveState
                            className={cn(
                                'min-w-8 rounded-md border px-2.5 py-1 text-center transition-colors hover:bg-accent focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                                link.active &&
                                    'border-primary bg-primary text-primary-foreground hover:bg-primary',
                            )}
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ) : (
                        <span
                            key={index}
                            className="min-w-8 rounded-md border px-2.5 py-1 text-center text-muted-foreground opacity-50"
                            dangerouslySetInnerHTML={{ __html: link.label }}
                        />
                    ),
                )}
            </div>
        </nav>
    );
}
