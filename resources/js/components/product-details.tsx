import { Download, ExternalLink, Globe, ImageOff, Layers, Tag, ZoomIn } from 'lucide-react';
import AppLogoIcon from '@/components/app-logo-icon';
import { ToneBadge } from '@/components/status-badge';
import { Button } from '@/components/ui/button';
import { Sheet, SheetContent, SheetDescription, SheetHeader, SheetTitle } from '@/components/ui/sheet';
import { formatTaka } from '@/lib/format';
import type { Product } from '@/types/catalogix';

export function StockBadge({ inStock }: { inStock: boolean | null }) {
    if (inStock === true) return <ToneBadge tone="success">In stock</ToneBadge>;
    if (inStock === false) return <ToneBadge tone="danger">Out of stock</ToneBadge>;
    return <span className="text-xs text-muted-foreground">Stock not specified</span>;
}

export function ProductDetails({ product, onOpenChange, onPreview }: {
    product: Product | null;
    onOpenChange: (open: boolean) => void;
    onPreview: () => void;
}) {
    const image = product?.image;
    const source = image?.url ?? image?.source_url;

    return (
        <Sheet open={product !== null} onOpenChange={onOpenChange}>
            <SheetContent className="w-full gap-0 overflow-hidden border-l-0 sm:w-[92vw] sm:max-w-4xl sm:rounded-l-3xl lg:inset-y-3 lg:h-[calc(100dvh-1.5rem)] xl:max-w-5xl">
                {product && <>
                    <div className="flex shrink-0 items-center justify-between gap-3 border-b bg-card px-5 py-4 pr-14 sm:px-7 sm:pr-14">
                        <div className="flex items-center gap-3">
                            <span className="flex size-10 items-center justify-center rounded-xl bg-primary text-primary-foreground shadow-sm shadow-primary/20"><AppLogoIcon className="size-6 fill-current" /></span>
                            <div><p className="text-base font-bold tracking-tight">Catalogix<span className="text-primary">.</span></p><p className="text-[11px] text-muted-foreground">Your product workspace</p></div>
                        </div>
                        <span className="hidden items-center gap-1.5 rounded-full border bg-muted/40 px-3 py-1 text-xs text-muted-foreground sm:flex"><Layers className="size-3.5" /> Product details</span>
                    </div>
                    <div className="min-h-0 flex-1 overflow-y-auto">
                        <div className="grid gap-7 p-5 sm:p-7 md:grid-cols-[minmax(0,0.95fr)_minmax(0,1.05fr)]">
                            <div className="space-y-4 md:sticky md:top-7 md:self-start">
                                <button type="button" disabled={!source} onClick={onPreview} aria-label={`Preview image of ${product.name}`} className="group relative flex aspect-square w-full items-center justify-center overflow-hidden rounded-2xl border bg-muted/25 p-4">
                                    {source ? <img src={source} referrerPolicy="no-referrer" alt={product.name} className="max-h-full w-full object-contain transition-transform duration-300 group-hover:scale-[1.03]" /> : <span className="flex flex-col items-center gap-2 text-muted-foreground"><ImageOff className="size-10" /> No picture available</span>}
                                    {source && <span className="absolute right-3 bottom-3 flex items-center gap-1.5 rounded-full border bg-background/95 px-3 py-1.5 text-xs shadow-sm"><ZoomIn className="size-3.5" /> Enlarge picture</span>}
                                </button>
                                <div className="grid grid-cols-2 gap-2">
                                    <Button variant="outline" disabled={!source} onClick={onPreview}><ZoomIn /> Preview</Button>
                                    {image?.download_url ? <Button asChild><a href={image.download_url} download><Download /> Download image</a></Button> : <Button disabled><Download /> Saving picture</Button>}
                                </div>
                                <div className="flex items-center justify-between gap-2 rounded-xl border bg-muted/20 px-3 py-2.5 text-xs text-muted-foreground">
                                    <span>{image?.status === 'downloaded' ? 'Picture saved locally' : 'Picture from the source website'}</span>
                                    {image?.width && image.height ? <span className="font-mono">{image.width} ? {image.height}</span> : null}
                                </div>
                            </div>
                            <div className="min-w-0 space-y-6">
                                <SheetHeader className="gap-3 p-0">
                                    <div className="flex flex-wrap items-center gap-2"><ToneBadge tone="brand"><Tag className="size-3" />{product.category ?? 'Uncategorized'}</ToneBadge><StockBadge inStock={product.in_stock} /></div>
                                    <SheetTitle className="text-2xl leading-tight font-semibold tracking-tight sm:text-3xl">{product.name}</SheetTitle>
                                    <SheetDescription className="flex items-center gap-2 text-sm"><span className="size-1.5 rounded-full bg-primary" />{product.brand ?? product.site}</SheetDescription>
                                </SheetHeader>
                                <div className="flex flex-wrap items-baseline gap-3 rounded-2xl border border-primary/10 bg-primary/5 p-4">
                                    <span className="font-mono text-3xl font-semibold tracking-tight">{formatTaka(product.sale_price ?? product.price)}</span>
                                    {product.sale_price !== null && <span className="font-mono text-sm text-muted-foreground line-through">{formatTaka(product.price)}</span>}
                                    {product.discount_percent ? <ToneBadge tone="success">Save {product.discount_percent}%</ToneBadge> : null}
                                </div>
                                <dl className="grid grid-cols-2 gap-4 rounded-2xl border p-4 text-sm">
                                    {([['Brand', product.brand], ['SKU', product.sku], ['Category', product.category], ['Subcategory', product.subcategory]] as const).map(([label, value]) => <div key={label} className="min-w-0 space-y-1"><dt className="text-xs text-muted-foreground">{label}</dt><dd className="break-words font-medium">{value || 'Not specified'}</dd></div>)}
                                </dl>
                                {product.description && <section className="space-y-2"><h3 className="text-sm font-semibold">About this product</h3><p className="text-sm leading-relaxed whitespace-pre-line text-muted-foreground">{product.description}</p></section>}
                                <section className="space-y-3 border-t pt-5">
                                    <h3 className="flex items-center gap-2 text-sm font-semibold"><Globe className="size-4 text-primary" /> Website & source</h3>
                                    <a href={product.website_url} target="_blank" rel="noreferrer" className="flex items-center justify-between gap-2 rounded-xl border bg-muted/20 px-3 py-2.5 text-sm font-medium hover:bg-muted/50">{product.site}<ExternalLink className="size-3.5 shrink-0 text-muted-foreground" /></a>
                                    {product.product_url && <a href={product.product_url} target="_blank" rel="noreferrer" className="block break-all text-xs leading-relaxed text-primary hover:underline">{product.product_url}</a>}
                                    {(product.sources?.length ?? 0) > 0 && <details className="rounded-xl border text-sm"><summary className="cursor-pointer px-3 py-2.5 text-muted-foreground">Collected from {product.sources?.length} source {product.sources?.length === 1 ? 'page' : 'pages'}</summary><div className="max-h-56 space-y-3 overflow-y-auto border-t p-3">{product.sources?.map((entry) => <div key={entry.id} className="space-y-1"><a href={entry.url} target="_blank" rel="noreferrer" className="flex items-center gap-1.5 text-primary hover:underline"><ExternalLink className="size-3 shrink-0" />{entry.category ?? 'Collection page'}</a><p className="text-xs break-all text-muted-foreground">{entry.url}</p><p className="text-[11px] text-muted-foreground">{entry.source?.replaceAll('_', ' ') ?? 'Website'}{entry.strategy && ` ? ${entry.strategy}`}</p></div>)}</div></details>}
                                </section>
                            </div>
                        </div>
                    </div>
                    <div className="flex shrink-0 items-center justify-between gap-3 border-t bg-card px-5 py-3 sm:px-7">
                        <p className="text-xs text-muted-foreground">Organized with <span className="font-semibold text-foreground">Catalogix</span></p>
                        {product.product_url && <Button asChild variant="outline" size="sm"><a href={product.product_url} target="_blank" rel="noreferrer">View on website<ExternalLink /></a></Button>}
                    </div>
                </>}
            </SheetContent>
        </Sheet>
    );
}
