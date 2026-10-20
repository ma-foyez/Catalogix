import { Download, ExternalLink, ImageOff, ZoomIn } from 'lucide-react';
import { ToneBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import {
    Sheet,
    SheetContent,
    SheetDescription,
    SheetHeader,
    SheetTitle,
} from '@/components/ui/sheet';
import { Spinner } from '@/components/ui/spinner';
import { formatTaka } from '@/lib/format';
import type { Product } from '@/types/catalogix';

export function StockBadge({ inStock }: { inStock: boolean | null }) {
    if (inStock === true) {
        return <ToneBadge tone="success">In stock</ToneBadge>;
    }

    if (inStock === false) {
        return <ToneBadge tone="danger">Out of stock</ToneBadge>;
    }

    return <span className="text-xs text-muted-foreground">—</span>;
}

/**
 * Everything known about one product, with its saved picture large — no need to visit the shop.
 */
export function ProductDetails({
    product,
    onOpenChange,
    onPreview,
}: {
    product: Product | null;
    onOpenChange: (open: boolean) => void;
    onPreview: () => void;
}) {
    const image = product?.image ?? null;

    return (
        <Sheet open={product !== null} onOpenChange={onOpenChange}>
            <SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-md">
                {product && (
                    <>
                        <div className="flex aspect-square items-center justify-center border-b bg-muted/40">
                            {image && (image.url ?? image.source_url) ? (
                                <button
                                    type="button"
                                    onClick={onPreview}
                                    className="group relative size-full"
                                    aria-label={`Preview image of ${product.name}`}
                                >
                                    <img
                                        src={image.url ?? image.source_url}
                                        referrerPolicy="no-referrer"
                                        alt={product.name}
                                        className="size-full object-contain"
                                    />
                                    <span className="absolute right-3 bottom-3 flex items-center gap-1 rounded-md bg-background/90 px-2 py-1 text-xs shadow-sm">
                                        <ZoomIn className="size-3.5" /> Enlarge
                                    </span>
                                </button>
                            ) : image?.status === 'pending' ? (
                                <span className="flex flex-col items-center gap-2 text-sm text-muted-foreground">
                                    <Spinner /> Saving the picture…
                                </span>
                            ) : (
                                <span className="flex flex-col items-center gap-2 text-sm text-muted-foreground">
                                    <ImageOff className="size-6" /> No picture
                                </span>
                            )}
                        </div>

                        <SheetHeader className="gap-2 p-5">
                            <SheetTitle className="text-lg leading-snug">
                                {product.name}
                            </SheetTitle>
                            <SheetDescription asChild>
                                <div className="flex flex-wrap items-baseline gap-2">
                                    <span className="font-mono text-xl font-semibold text-foreground">
                                        {formatTaka(
                                            product.sale_price ?? product.price,
                                        )}
                                    </span>
                                    {product.sale_price && (
                                        <span className="font-mono text-sm line-through">
                                            {formatTaka(product.price)}
                                        </span>
                                    )}
                                    {product.discount_percent ? (
                                        <ToneBadge tone="success">
                                            {product.discount_percent}% off
                                        </ToneBadge>
                                    ) : null}
                                    <StockBadge inStock={product.in_stock} />
                                </div>
                            </SheetDescription>
                        </SheetHeader>

                        <div className="flex gap-2 px-5 pb-4">
                            {image && (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={onPreview}
                                >
                                    <ZoomIn /> Preview image
                                </Button>
                            )}
                            {image?.download_url && (
                                <Button size="sm" asChild>
                                    <a href={image.download_url} download>
                                        <Download /> Download image
                                    </a>
                                </Button>
                            )}
                        </div>

                        <dl className="grid grid-cols-[7rem_1fr] gap-x-3 gap-y-2.5 border-t px-5 py-4 text-sm">
                            {(
                                [
                                    [
                                        'Category',
                                        product.category ?? 'Uncategorized',
                                    ],
                                    ['Subcategory', product.subcategory],
                                    ['Brand', product.brand],
                                    ['SKU', product.sku],
                                    ['Website', product.site],
                                ] as const
                            ).map(([label, value]) => (
                                <div key={label} className="contents">
                                    <dt className="text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="min-w-0 break-words">
                                        {value || (
                                            <span className="text-muted-foreground">
                                                —
                                            </span>
                                        )}
                                    </dd>
                                </div>
                            ))}
                        </dl>

                        <div className="space-y-3 border-t px-5 py-4 text-sm">
                            <p className="font-medium">Website & sources</p>
                            <a
                                href={product.website_url}
                                target="_blank"
                                rel="noreferrer"
                                className="flex items-center gap-1.5 break-all text-primary hover:underline"
                            >
                                <ExternalLink className="size-3.5 shrink-0" />
                                {product.site}
                            </a>
                            {product.product_url && (
                                <a
                                    href={product.product_url}
                                    target="_blank"
                                    rel="noreferrer"
                                    className="block break-all text-primary hover:underline"
                                >
                                    {product.product_url}
                                </a>
                            )}
                            <div className="max-h-56 space-y-2 overflow-y-auto">
                                {product.sources?.map((source) => (
                                    <div
                                        key={source.id}
                                        className="space-y-1 rounded-lg border bg-muted/30 p-3"
                                    >
                                        <a
                                            href={source.url}
                                            target="_blank"
                                            rel="noreferrer"
                                            className="flex items-center gap-1.5 text-primary hover:underline"
                                        >
                                            <ExternalLink className="size-3.5 shrink-0" />
                                            {source.category ??
                                                'Collection page'}
                                        </a>
                                        <p className="text-xs break-all text-muted-foreground">
                                            {source.url}
                                        </p>
                                        <p className="text-xs text-muted-foreground">
                                            {source.source?.replaceAll(
                                                '_',
                                                ' ',
                                            ) ?? 'Website'}
                                            {source.strategy &&
                                                ` · ${source.strategy}`}
                                        </p>
                                    </div>
                                ))}
                            </div>
                        </div>

                        {product.description && (
                            <div className="space-y-1.5 border-t px-5 py-4">
                                <p className="text-sm font-medium">
                                    Description
                                </p>
                                <p className="text-sm whitespace-pre-line text-muted-foreground">
                                    {product.description}
                                </p>
                            </div>
                        )}

                        {(product.product_url || image?.download_url) && (
                            <div className="border-t p-5">
                                {image?.download_url && (
                                    <Button asChild className="mb-2 w-full">
                                        <a href={image.download_url} download>
                                            <Download /> Download image
                                        </a>
                                    </Button>
                                )}
                                {product.product_url && (
                                    <Button
                                        asChild
                                        variant="outline"
                                        className="w-full"
                                    >
                                        <a
                                            href={product.product_url}
                                            target="_blank"
                                            rel="noreferrer"
                                        >
                                            <ExternalLink /> Open on the website
                                        </a>
                                    </Button>
                                )}
                            </div>
                        )}
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
