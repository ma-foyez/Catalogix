import { Head, router, usePage } from '@inertiajs/react';
import {
    Check,
    Eye,
    Hammer,
    MousePointerClick,
    RotateCcw,
    Save,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import RecipeController from '@/actions/App/Http/Controllers/RecipeController';
import InputError from '@/components/input-error';
import { ToneBadge } from '@/components/status-badge';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { formatTaka } from '@/lib/format';
import { cn } from '@/lib/utils';
import { index as sitesIndex } from '@/routes/sites';
import { edit } from '@/routes/sites/recipe';

type Field =
    | 'card'
    | 'name'
    | 'price'
    | 'sale_price'
    | 'image'
    | 'link'
    | 'next';
type Selectors = Record<Field, string>;

type PreviewProduct = {
    name: string;
    price: number | null;
    sale_price: number | null;
    image_url: string | null;
    product_url: string | null;
};

const PICK_STEPS: {
    field: Field;
    label: string;
    hint: string;
    optional?: boolean;
}[] = [
    {
        field: 'name',
        label: 'Product name',
        hint: 'Click the name of any one product.',
    },
    {
        field: 'price',
        label: 'Price',
        hint: 'Click its price (the box holding both prices if it is on sale).',
    },
    { field: 'image', label: 'Image', hint: 'Click its picture.' },
    {
        field: 'sale_price',
        label: 'Sale price',
        hint: 'Only if the sale price sits apart from the price.',
        optional: true,
    },
    {
        field: 'next',
        label: 'Next button',
        hint: 'The link to the next page of products.',
        optional: true,
    },
];

const EMPTY: Selectors = {
    card: '',
    name: '',
    price: '',
    sale_price: '',
    image: '',
    link: '',
    next: '',
};

function xsrfToken(): string {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]+)/);

    return match ? decodeURIComponent(match[1]) : '';
}

/*
 * Design brief
 * User: admin teaching Catalogix a layout the automatic ladder could not read.
 * Main task: click one name, price, image and Next on the real page, check all matches, save.
 * Key information: the live page, which fields are picked, generated selectors, preview of matched products.
 * Primary action: "Save recipe".
 * States: page loading, picking, recipe built, preview with N products, preview with 0 (error), saved.
 */
export default function RecipeEdit({
    site,
    url,
    frameUrl,
}: {
    site: {
        id: number;
        domain: string;
        needs_js: boolean;
        recipe_valid: boolean;
        selectors: Partial<Selectors> | null;
    };
    url: string;
    frameUrl: string;
}) {
    const frame = useRef<HTMLIFrameElement>(null);
    const { errors } = usePage<{ errors: Record<string, string> }>().props;
    const [pageUrl, setPageUrl] = useState(url);
    const [ready, setReady] = useState(false);
    const [picking, setPicking] = useState<Field | null>(null);
    const [samples, setSamples] = useState<Partial<Record<Field, string>>>({});
    const [selectors, setSelectors] = useState<Selectors>({
        ...EMPTY,
        ...Object.fromEntries(
            Object.entries(site.selectors ?? {}).map(([k, v]) => [k, v ?? '']),
        ),
    });
    const [message, setMessage] = useState<string | null>(null);
    const [preview, setPreview] = useState<{
        cards: number;
        products: PreviewProduct[];
    } | null>(null);
    const [busy, setBusy] = useState<'preview' | 'save' | null>(null);

    const post = (message: Record<string, unknown>) =>
        frame.current?.contentWindow?.postMessage(message, '*');

    useEffect(() => {
        const onMessage = (event: MessageEvent) => {
            if (event.source !== frame.current?.contentWindow) {
                return;
            }

            const data = event.data ?? {};

            if (data.type === 'catalogix:ready') {
                setReady(true);
            }

            if (data.type === 'catalogix:picked') {
                setPicking(null);
                setSamples((current) => ({
                    ...current,
                    [data.field]:
                        data.sample?.text || data.sample?.src || data.path,
                }));

                if (data.field === 'next') {
                    setSelectors((current) => ({
                        ...current,
                        next: data.path,
                    }));
                }
            }

            if (data.type === 'catalogix:recipe') {
                setSelectors((current) => ({
                    ...current,
                    ...data.selectors,
                    next: data.selectors.next ?? current.next,
                }));
                setMessage(
                    `The recipe matches ${data.count} product cards on this page.`,
                );
            }

            if (data.type === 'catalogix:error') {
                setMessage(data.message);
            }
        };

        window.addEventListener('message', onMessage);

        return () => window.removeEventListener('message', onMessage);
    }, []);

    const pick = (field: Field) => {
        setPicking(field);
        setMessage(null);
        post({ type: 'catalogix:pick', field });
    };

    const runPreview = async () => {
        setBusy('preview');
        setPreview(null);

        try {
            const response = await fetch(
                RecipeController.preview.url(site.id),
                {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        Accept: 'application/json',
                        'X-XSRF-TOKEN': xsrfToken(),
                    },
                    body: JSON.stringify({ url: pageUrl, selectors }),
                },
            );
            const data = await response.json();

            if (!response.ok) {
                setMessage(data.message ?? 'The preview failed.');
            } else {
                setPreview(data);
                post({ type: 'catalogix:highlight', selector: selectors.card });
            }
        } finally {
            setBusy(null);
        }
    };

    const save = () => {
        setBusy('save');
        router.put(
            RecipeController.update.url(site.id),
            { url: pageUrl, selectors },
            { onFinish: () => setBusy(null) },
        );
    };

    const reset = () => {
        setSamples({});
        setSelectors(EMPTY);
        setPreview(null);
        setMessage(null);
        post({ type: 'catalogix:reset' });
    };

    return (
        <>
            <Head title={`Recipe · ${site.domain}`} />

            <div className="grid min-h-0 flex-1 gap-4 p-4 lg:grid-cols-[22rem_1fr]">
                <aside className="flex flex-col gap-5">
                    <header className="space-y-1">
                        <h1 className="text-lg font-semibold">
                            Visual recipe picker
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            Teach Catalogix where products sit on{' '}
                            <span className="font-mono">{site.domain}</span>.
                            Pick from one product; the rest are found
                            automatically.
                        </p>
                        {site.recipe_valid ? (
                            <ToneBadge tone="success">
                                Current recipe works
                            </ToneBadge>
                        ) : site.selectors?.card ? (
                            <ToneBadge tone="danger">
                                Current recipe flagged
                            </ToneBadge>
                        ) : null}
                    </header>

                    <form
                        className="flex gap-2"
                        onSubmit={(event) => {
                            event.preventDefault();
                            router.get(
                                edit.url(site.id, { query: { url: pageUrl } }),
                            );
                        }}
                    >
                        <Label htmlFor="page-url" className="sr-only">
                            Page URL
                        </Label>
                        <Input
                            id="page-url"
                            value={pageUrl}
                            onChange={(event) => setPageUrl(event.target.value)}
                            className="font-mono text-xs"
                        />
                        <Button type="submit" variant="outline">
                            Load
                        </Button>
                    </form>

                    <ol className="space-y-2">
                        {PICK_STEPS.map((step, index) => (
                            <li
                                key={step.field}
                                className={cn(
                                    'rounded-lg border p-3',
                                    picking === step.field &&
                                        'border-primary ring-2 ring-primary/30',
                                )}
                            >
                                <div className="flex items-center justify-between gap-2">
                                    <p className="text-sm font-medium">
                                        {index + 1}. {step.label}
                                        {step.optional && (
                                            <span className="ml-1 text-xs font-normal text-muted-foreground">
                                                optional
                                            </span>
                                        )}
                                    </p>
                                    <Button
                                        size="sm"
                                        variant={
                                            samples[step.field]
                                                ? 'outline'
                                                : 'default'
                                        }
                                        disabled={!ready}
                                        onClick={() => pick(step.field)}
                                    >
                                        {samples[step.field] ? (
                                            <Check />
                                        ) : (
                                            <MousePointerClick />
                                        )}
                                        {picking === step.field
                                            ? 'Click on page…'
                                            : samples[step.field]
                                              ? 'Re-pick'
                                              : 'Pick'}
                                    </Button>
                                </div>
                                <p
                                    className="mt-1 truncate text-xs text-muted-foreground"
                                    title={samples[step.field]}
                                >
                                    {samples[step.field] ?? step.hint}
                                </p>
                            </li>
                        ))}
                    </ol>

                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="secondary"
                            disabled={!samples.name || !samples.price}
                            onClick={() => post({ type: 'catalogix:build' })}
                        >
                            <Hammer /> Build recipe
                        </Button>
                        <Button variant="ghost" onClick={reset}>
                            <RotateCcw /> Start over
                        </Button>
                    </div>

                    {message && (
                        <Alert>
                            <AlertDescription>{message}</AlertDescription>
                        </Alert>
                    )}

                    <details
                        className="rounded-lg border p-3"
                        open={Boolean(selectors.card)}
                    >
                        <summary className="cursor-pointer text-sm font-medium">
                            Selectors
                        </summary>
                        <div className="mt-3 space-y-2">
                            {(Object.keys(EMPTY) as Field[]).map((field) => (
                                <div
                                    key={field}
                                    className="grid grid-cols-[5.5rem_1fr] items-center gap-2"
                                >
                                    <Label
                                        htmlFor={`selector-${field}`}
                                        className="text-xs capitalize"
                                    >
                                        {field.replace('_', ' ')}
                                    </Label>
                                    <Input
                                        id={`selector-${field}`}
                                        value={selectors[field]}
                                        onChange={(event) =>
                                            setSelectors((current) => ({
                                                ...current,
                                                [field]: event.target.value,
                                            }))
                                        }
                                        className="h-8 font-mono text-xs"
                                    />
                                </div>
                            ))}
                        </div>
                        <InputError
                            message={
                                errors.selectors ?? errors['selectors.card']
                            }
                            className="mt-2"
                        />
                    </details>

                    <div className="flex flex-wrap gap-2">
                        <Button
                            variant="outline"
                            disabled={!selectors.card || busy !== null}
                            onClick={runPreview}
                        >
                            {busy === 'preview' ? <Spinner /> : <Eye />} Preview
                            matches
                        </Button>
                        <Button
                            disabled={
                                !selectors.card ||
                                busy !== null ||
                                (preview !== null &&
                                    preview.products.length === 0)
                            }
                            onClick={save}
                        >
                            {busy === 'save' ? <Spinner /> : <Save />} Save
                            recipe
                        </Button>
                    </div>

                    {preview && (
                        <section className="space-y-2">
                            <p className="text-sm">
                                <span className="font-medium">
                                    {preview.products.length}
                                </span>{' '}
                                products from {preview.cards} cards
                            </p>
                            <ul className="max-h-72 divide-y overflow-y-auto rounded-lg border">
                                {preview.products
                                    .slice(0, 20)
                                    .map((product, index) => (
                                        <li
                                            key={index}
                                            className="flex items-center gap-2 p-2"
                                        >
                                            {product.image_url ? (
                                                <img
                                                    src={product.image_url}
                                                    alt=""
                                                    referrerPolicy="no-referrer"
                                                    className="size-9 rounded border object-cover"
                                                />
                                            ) : (
                                                <span className="size-9 rounded border bg-muted" />
                                            )}
                                            <span className="min-w-0 flex-1 truncate text-xs">
                                                {product.name}
                                            </span>
                                            <span className="font-mono text-xs whitespace-nowrap">
                                                {formatTaka(
                                                    product.sale_price ??
                                                        product.price,
                                                )}
                                            </span>
                                        </li>
                                    ))}
                            </ul>
                        </section>
                    )}
                </aside>

                <section className="relative min-h-[70vh] overflow-hidden rounded-xl border bg-white">
                    {!ready && (
                        <div className="absolute inset-0 flex items-center justify-center gap-2 bg-background/80 text-sm text-muted-foreground">
                            <Spinner /> Loading{' '}
                            {site.needs_js ? 'and rendering ' : ''}the page…
                        </div>
                    )}
                    <iframe
                        ref={frame}
                        src={frameUrl}
                        title={`${site.domain} page`}
                        sandbox="allow-scripts"
                        className="size-full min-h-[70vh]"
                    />
                </section>
            </div>
        </>
    );
}

RecipeEdit.layout = {
    breadcrumbs: [
        { title: 'Sites & recipes', href: sitesIndex() },
        { title: 'Recipe picker', href: '#' },
    ],
};
