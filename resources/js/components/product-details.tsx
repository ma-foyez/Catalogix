import { ExternalLink, ImageOff } from 'lucide-react';
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
}: {
    product: Product | null;
    onOpenChange: (open: boolean) => void;
}) {
    const image = product?.image ?? null;

    return (
        <Sheet open={product !== null} onOpenChange={onOpenChange}>
            <SheetContent className="w-full gap-0 overflow-y-auto sm:max-w-md">
                {product && (
                    <>
                        <div className="flex aspect-square items-center justify-center border-b bg-muted/40">
                            {image?.status === 'downloaded' &&
                            (image.url ?? image.thumb_url) ? (
                                <img
                                    src={image.url ?? image.thumb_url ?? ''}
                                    alt={product.name}
                                    className="size-full object-contain"
                                />
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

                        <dl className="grid grid-cols-[7rem_1fr] gap-x-3 gap-y-2.5 border-t px-5 py-4 text-sm">
                            {(
                                [
                                    ['Category', product.category],
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

                        {product.product_url && (
                            <div className="border-t p-5">
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
                            </div>
                        )}
                    </>
                )}
            </SheetContent>
        </Sheet>
    );
}
