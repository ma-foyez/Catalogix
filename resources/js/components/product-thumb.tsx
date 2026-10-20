import { ImageOff, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import type { ProductImage } from '@/types/catalogix';

/**
 * The product's local thumbnail; clicking opens the full local image.
 * Shows a spinner while the download is pending and a crossed icon when it failed.
 */
export function ProductThumb({
    image,
    name,
    className,
}: {
    image: ProductImage | null;
    name: string;
    className?: string;
}) {
    const box = cn(
        'flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted',
        className,
    );

    if (image?.status === 'downloaded' && image.thumb_url) {
        return (
            <a
                href={image.url ?? image.thumb_url}
                target="_blank"
                rel="noreferrer"
                className={cn(
                    box,
                    'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                )}
            >
                <img
                    src={image.thumb_url}
                    alt={name}
                    loading="lazy"
                    className="size-full object-cover"
                />
            </a>
        );
    }

    if (image?.status === 'failed') {
        return (
            <span
                className={cn(box, 'text-muted-foreground')}
                title={image.error ?? 'Image download failed'}
            >
                <ImageOff className="size-4" aria-label="Image failed" />
            </span>
        );
    }

    return (
        <span
            className={cn(box, 'text-muted-foreground')}
            title={image ? 'Downloading image…' : 'No image found'}
        >
            {image ? (
                <Loader2
                    className="size-4 animate-spin"
                    aria-label="Downloading image"
                />
            ) : (
                <ImageOff className="size-4 opacity-40" aria-label="No image" />
            )}
        </span>
    );
}
