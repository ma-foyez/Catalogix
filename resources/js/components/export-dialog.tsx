import { router } from '@inertiajs/react';
import { FileDown } from 'lucide-react';
import { useState } from 'react';
import ExportController from '@/actions/App/Http/Controllers/ExportController';
import { Segmented } from '@/components/segmented';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatCount } from '@/lib/format';
import type {
    ExportFormat,
    ExportLayout,
    ExportScope,
    ScrapeTarget,
} from '@/types/catalogix';

const FORMAT_HELP: Record<ExportFormat, string> = {
    xlsx_images:
        'The product picture sits right in each row, next to name, SKU, category, subcategory, prices, stock and description. Over 3,000 products it is split into several files inside one ZIP.',
    xlsx: 'Smaller file with the same details, but picture links instead of the pictures.',
    csv: 'Plain text for other software, with picture links. Opens in Excel with Bengali text and ৳ intact.',
    zip_images: 'Just the pictures, in folders per website and category.',
};

export function ExportDialog({
    jobId,
    domain,
    route,
    totalProducts,
}: {
    jobId: number;
    domain: string | null;
    route: ScrapeTarget | null;
    totalProducts: number;
}) {
    const [open, setOpen] = useState(false);
    const [scope, setScope] = useState<ExportScope>(route ? 'route' : 'site');
    const [format, setFormat] = useState<ExportFormat>('xlsx_images');
    const [layout, setLayout] = useState<ExportLayout>('per_site');
    const [submitting, setSubmitting] = useState(false);

    const submit = () => {
        setSubmitting(true);
        router.post(
            ExportController.store.url(),
            {
                scope,
                format,
                layout,
                job_id: scope === 'all' ? null : jobId,
                route_id: scope === 'route' ? route?.id : null,
            },
            {
                preserveScroll: true,
                onSuccess: () => setOpen(false),
                onFinish: () => setSubmitting(false),
            },
        );
    };

    return (
        <Dialog open={open} onOpenChange={setOpen}>
            <DialogTrigger asChild>
                <Button variant="outline" disabled={totalProducts === 0}>
                    <FileDown /> Other formats
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Download in another format</DialogTitle>
                    <DialogDescription>
                        The file is prepared in the background and appears under
                        Downloads, where it can be downloaded for 24 hours.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-5">
                    <div className="space-y-2">
                        <Label>Which products</Label>
                        <Segmented
                            label="Scope"
                            value={scope}
                            onChange={setScope}
                            options={[
                                {
                                    value: 'route',
                                    label: 'This section',
                                    disabled: route === null,
                                },
                                { value: 'site', label: domain ?? 'This site' },
                                { value: 'all', label: 'All websites' },
                            ]}
                        />
                        <p className="text-xs text-muted-foreground">
                            {scope === 'route' &&
                                route &&
                                `${route.category ?? route.url} · ${formatCount(route.products_found)} products`}
                            {scope === 'site' &&
                                `Everything collected from this website · ${formatCount(totalProducts)} products`}
                            {scope === 'all' &&
                                'Every product from all your websites'}
                        </p>
                    </div>

                    <div className="space-y-2">
                        <Label>Format</Label>
                        <Segmented
                            label="Format"
                            value={format}
                            onChange={setFormat}
                            options={[
                                {
                                    value: 'xlsx_images',
                                    label: 'Excel + pictures',
                                },
                                { value: 'xlsx', label: 'Excel (links)' },
                                { value: 'csv', label: 'CSV' },
                                { value: 'zip_images', label: 'Pictures ZIP' },
                            ]}
                        />
                        <p className="text-xs text-muted-foreground">
                            {FORMAT_HELP[format]}
                        </p>
                    </div>

                    {(format === 'xlsx' || format === 'xlsx_images') && (
                        <div className="space-y-2">
                            <Label>Sheets</Label>
                            <Segmented
                                label="Layout"
                                value={layout}
                                onChange={setLayout}
                                options={[
                                    {
                                        value: 'per_site',
                                        label: 'One per website',
                                    },
                                    {
                                        value: 'single',
                                        label: 'All in one sheet',
                                    },
                                ]}
                            />
                        </div>
                    )}
                </div>

                <DialogFooter>
                    <Button variant="outline" onClick={() => setOpen(false)}>
                        Cancel
                    </Button>
                    <Button onClick={submit} disabled={submitting}>
                        {submitting ? <Spinner /> : <FileDown />}
                        Prepare file
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
