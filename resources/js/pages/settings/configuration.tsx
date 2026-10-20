import { Form, Head } from '@inertiajs/react';
import { Gauge, Layers, Save, SlidersHorizontal } from 'lucide-react';
import ConfigurationController from '@/actions/App/Http/Controllers/Settings/ConfigurationController';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import type { CollectionConfiguration } from '@/types/catalogix';

export default function Configuration({ configuration, limits }: { configuration: CollectionConfiguration; limits: { max_websites: number; max_routes: number; max_pages: number } }) {
    return <>
        <Head title="Collection configuration" />
        <div className="space-y-6">
            <Heading variant="small" title="Collection configuration" description="Set the defaults for how much Catalogix collects from each website." />
            <div className="flex items-start gap-3 rounded-xl border border-primary/15 bg-primary/5 p-4 text-sm"><SlidersHorizontal className="mt-0.5 size-5 shrink-0 text-primary" /><p className="leading-relaxed text-muted-foreground">These settings apply to <span className="font-medium text-foreground">new collections in your account</span>. Existing collections keep their original limits.</p></div>
            <Form {...ConfigurationController.update.form()} className="space-y-6">{({ processing, errors, recentlySuccessful }) => <>
                <section className="space-y-5 rounded-2xl border bg-card p-5">
                    <div className="flex items-center gap-2"><Gauge className="size-4 text-primary" /><h2 className="font-semibold">Products per website</h2></div>
                    <div className="grid gap-5 sm:grid-cols-2">
                        <div className="space-y-2"><Label htmlFor="min_products">Minimum target</Label><Input id="min_products" name="min_products" type="number" min={1} max={100000} required defaultValue={configuration.min_products} /><InputError message={errors.min_products} /></div>
                        <div className="space-y-2"><Label htmlFor="max_products">Maximum products</Label><Input id="max_products" name="max_products" type="number" min={1} max={100000} defaultValue={configuration.max_products ?? ''} placeholder="No product limit" /><InputError message={errors.max_products} /></div>
                    </div>
                    <p className="text-xs leading-relaxed text-muted-foreground">The maximum stops collection at that many unique products. Leave it blank for no product cap. The minimum is a target: if the website has fewer readable products, Catalogix saves the available results and tells you.</p>
                </section>
                <section className="space-y-5 rounded-2xl border bg-card p-5">
                    <div className="flex items-center gap-2"><Layers className="size-4 text-primary" /><h2 className="font-semibold">Collection size</h2></div>
                    {([
                        ['max_websites', 'Websites per batch', `Up to ${limits.max_websites} website addresses at a time.`],
                        ['max_routes', 'Sections per website', `Automatically collect up to ${limits.max_routes} recommended sections.`],
                        ['max_pages', 'Pages per section', `Follow pagination for up to ${limits.max_pages} pages per section.`],
                    ] as const).map(([key, label, help]) => <div key={key} className="grid items-start gap-3 border-b pb-4 last:border-0 last:pb-0 sm:grid-cols-[1fr_7rem]"><div className="space-y-1"><Label htmlFor={key}>{label}</Label><p className="text-xs text-muted-foreground">{help}</p><InputError message={errors[key]} /></div><Input id={key} name={key} type="number" min={1} max={limits[key]} required defaultValue={configuration[key]} /></div>)}
                </section>
                <div className="flex items-center gap-3"><Button disabled={processing}>{processing ? <Spinner /> : <Save />} Save configuration</Button>{recentlySuccessful && <p role="status" className="text-sm text-success">Saved</p>}</div>
            </>}</Form>
        </div>
    </>;
}

Configuration.layout = { breadcrumbs: [{ title: 'Settings', href: '/settings/profile' }, { title: 'Configuration', href: '/settings/configuration' }] };
