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
        'Each row carries the 200 px product thumbnail, so the file reads like a catalogue offline. Over 3,000 rows it splits into one file per site or category, delivered as a ZIP.',
    xlsx: 'Fast and small: every column, image URLs and local paths instead of pictures.',
    csv: 'One CSV with a Source column, UTF-8 with BOM so Excel shows Bengali text and ৳ correctly.',
    zip_images: 'The downloaded images in folders per site and category.',
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
                <Button disabled={totalProducts === 0}>
                    <FileDown /> Export
                </Button>
            </DialogTrigger>
            <DialogContent className="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Export products</DialogTitle>
                    <DialogDescription>
                        The file is built in the background; you will find it
                        under Exports with a download link valid for 24 hours.
                    </DialogDescription>
                </DialogHeader>

                <div className="space-y-5">
                    <div className="space-y-2">
                        <Label>Scope</Label>
                        <Segmented
                            label="Scope"
                            value={scope}
                            onChange={setScope}
                            options={[
                                {
                                    value: 'route',
                                    label: 'This route',
                                    disabled: route === null,
                                },
                                { value: 'site', label: domain ?? 'This site' },
                                { value: 'all', label: 'All sites' },
                            ]}
                        />
                        <p className="text-xs text-muted-foreground">
                            {scope === 'route' &&
                                route &&
                                `${route.category ?? route.url} · ${formatCount(route.products_found)} products`}
                            {scope === 'site' &&
                                `Every route of this job · ${formatCount(totalProducts)} products`}
                            {scope === 'all' &&
                                'Every product from all your extractions'}
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
                                    label: 'Excel + images',
                                },
                                { value: 'xlsx', label: 'Excel' },
                                { value: 'csv', label: 'CSV' },
                                { value: 'zip_images', label: 'Images ZIP' },
                            ]}
                        />
                        <p className="text-xs text-muted-foreground">
                            {FORMAT_HELP[format]}
                        </p>
                    </div>

                    {(format === 'xlsx' || format === 'xlsx_images') && (
                        <div className="space-y-2">
                            <Label>Layout</Label>
                            <Segmented
                                label="Layout"
                                value={layout}
                                onChange={setLayout}
                                options={[
                                    {
                                        value: 'per_site',
                                        label: 'Sheet per site',
                                    },
                                    {
                                        value: 'single',
                                        label: 'One sheet + Source',
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
                        Start export
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    );
}
