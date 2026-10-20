import { Download, ImageOff } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import type { Product } from '@/types/catalogix';

export function ProductImagePreview({
    product,
    onOpenChange,
}: {
    product: Product | null;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={product !== null} onOpenChange={onOpenChange}>
            <DialogContent className="max-h-[95dvh] overflow-y-auto sm:max-w-4xl">
                {product && (
                    <>
                        <DialogHeader className="pr-7">
                            <DialogTitle>{product.name}</DialogTitle>
                            <DialogDescription>
                                {product.site} ·{' '}
                                {product.category ?? 'Uncategorized'}
                            </DialogDescription>
                        </DialogHeader>
                        <PreviewPicture
                            key={`${product.id}-${product.image?.url}`}
                            product={product}
                        />
                        <div className="flex flex-wrap items-center justify-between gap-3">
                            <p className="text-xs text-muted-foreground">
                                {product.image?.width && product.image.height
                                    ? `${product.image.width} × ${product.image.height} px`
                                    : 'Product picture'}
                                {product.image?.status !== 'downloaded' &&
                                    ' · Preview from the source website. The local copy is still being prepared.'}
                            </p>
                            {product.image?.download_url ? (
                                <Button asChild>
                                    <a
                                        href={product.image.download_url}
                                        download
                                    >
                                        <Download /> Download image
                                    </a>
                                </Button>
                            ) : (
                                <Button disabled>
                                    <Download /> Download when saved
                                </Button>
                            )}
                        </div>
                    </>
                )}
            </DialogContent>
        </Dialog>
    );
}

function PreviewPicture({ product }: { product: Product }) {
    const [failed, setFailed] = useState(false);
    const source = product.image?.url ?? product.image?.source_url;

    return (
        <div className="flex min-h-48 items-center justify-center rounded-xl border bg-muted/30">
            {source && !failed ? (
                <img
                    src={source}
                    alt={product.name}
                    referrerPolicy="no-referrer"
                    onError={() => setFailed(true)}
                    className="max-h-[65dvh] w-full object-contain"
                />
            ) : (
                <span className="flex items-center gap-2 p-12 text-sm text-muted-foreground">
                    <ImageOff /> This picture could not be loaded.
                </span>
            )}
        </div>
    );
}
