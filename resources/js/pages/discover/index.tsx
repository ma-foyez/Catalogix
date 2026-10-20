import { Form, Head, Link } from '@inertiajs/react';
import { ArrowRight, Globe, Radar } from 'lucide-react';
import DiscoveryController from '@/actions/App/Http/Controllers/DiscoveryController';
import { EmptyState } from '@/components/empty-state';
import InputError from '@/components/input-error';
import { StatusBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { timeAgo } from '@/lib/format';
import { discover } from '@/routes';
import { show } from '@/routes/discoveries';
import type { Discovery } from '@/types/catalogix';

const EXAMPLES = ['chaldal.com', 'v2.sawdahexpress.com.bd', 'shajgoj.com'];

/*
 * Design brief
 * User: seller or sourcing agent who wants another shop's catalogue.
 * Main task: type only a domain and start route discovery.
 * Key information: what Catalogix will do next; recent discoveries and how they went.
 * Primary action: "Find product routes".
 * States: empty history, validation error, submitting, recent list with live statuses.
 */
export default function DiscoverIndex({
    recent,
}: {
    recent: { data: Discovery[] };
}) {
    return (
        <>
            <Head title="Discover" />

            <div className="mx-auto flex w-full max-w-3xl flex-col gap-10 px-4 py-8 md:py-14">
                <section className="space-y-6">
                    <div className="space-y-2">
                        <h1 className="text-2xl font-semibold tracking-tight md:text-3xl">
                            Find every product route on a site
                        </h1>
                        <p className="text-sm text-muted-foreground md:text-base">
                            Enter a shop's domain. Catalogix reads its sitemap,
                            menu and catalogue, then suggests the pages that
                            list products — you tick the ones to extract.
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
                                <div className="flex flex-col gap-2 sm:flex-row">
                                    <div className="relative flex-1">
                                        <Globe className="pointer-events-none absolute top-1/2 left-3 size-4 -translate-y-1/2 text-muted-foreground" />
                                        <Input
                                            id="domain"
                                            name="domain"
                                            placeholder="chaldal.com"
                                            autoComplete="off"
                                            autoFocus
                                            required
                                            aria-invalid={
                                                errors.domain ? true : undefined
                                            }
                                            className="h-11 pl-9 font-mono text-base"
                                        />
                                    </div>
                                    <Button
                                        type="submit"
                                        size="lg"
                                        className="h-11"
                                        disabled={processing}
                                    >
                                        {processing ? <Spinner /> : <Radar />}
                                        Find product routes
                                    </Button>
                                </div>
                                <InputError message={errors.domain} />
                                <p className="text-xs text-muted-foreground">
                                    Try{' '}
                                    {EXAMPLES.map((example, index) => (
                                        <span key={example}>
                                            <code className="rounded bg-muted px-1 py-0.5 font-mono">
                                                {example}
                                            </code>
                                            {index < EXAMPLES.length - 1
                                                ? ', '
                                                : '. '}
                                        </span>
                                    ))}
                                    Pages behind a login, CAPTCHA or bot check
                                    are marked blocked, never bypassed.
                                </p>
                            </>
                        )}
                    </Form>
                </section>

                <section className="space-y-3">
                    <h2 className="text-sm font-medium text-muted-foreground">
                        Recent discoveries
                    </h2>

                    {recent.data.length === 0 ? (
                        <EmptyState
                            icon={Radar}
                            title="No discoveries yet"
                            description="Your first domain will show up here with the routes it found."
                        />
                    ) : (
                        <ul className="divide-y rounded-xl border">
                            {recent.data.map((discovery) => (
                                <li key={discovery.id}>
                                    <Link
                                        href={show(discovery.id)}
                                        className="flex items-center gap-3 px-4 py-3 transition-colors hover:bg-muted/50 focus-visible:bg-muted/50 focus-visible:outline-none"
                                    >
                                        <span className="min-w-0 flex-1">
                                            <span className="block truncate font-mono text-sm">
                                                {discovery.domain}
                                            </span>
                                            <span className="text-xs text-muted-foreground">
                                                {discovery.status === 'done'
                                                    ? `${discovery.routes_found} product routes`
                                                    : discovery.status ===
                                                        'failed'
                                                      ? (discovery.error ??
                                                        'Failed')
                                                      : 'Looking for routes…'}{' '}
                                                ·{' '}
                                                {timeAgo(discovery.created_at)}
                                            </span>
                                        </span>
                                        <StatusBadge
                                            status={discovery.status}
                                        />
                                        <ArrowRight className="size-4 text-muted-foreground" />
                                    </Link>
                                </li>
                            ))}
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
