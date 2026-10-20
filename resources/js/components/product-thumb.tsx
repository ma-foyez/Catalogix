import { ImageOff, Loader2 } from 'lucide-react';
import { cn } from '@/lib/utils';
import { useState } from 'react';
import type { ProductImage } from '@/types/catalogix';

/**
 * Opens an in-page preview, using the source picture until the local thumbnail is ready.
 */
export function ProductThumb({
    image,
    name,
    className,
    onPreview,
}: {
    image: ProductImage | null;
    name: string;
    className?: string;
    onPreview: () => void;
}) {
    const [failedSource, setFailedSource] = useState<string | null>(null);
    const source = image?.thumb_url ?? image?.url ?? image?.source_url;
    const box = cn(
        'flex size-12 shrink-0 items-center justify-center overflow-hidden rounded-md border bg-muted',
        className,
    );

    if (source && failedSource !== source) {
        return (
            <button
                type="button"
                onClick={onPreview}
                aria-label={`Preview image of ${name}`}
                className={cn(
                    box,
                    'focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none',
                )}
            >
                <img
                    src={source}
                    referrerPolicy="no-referrer"
                    onError={() => setFailedSource(source)}
                    alt={name}
                    loading="lazy"
                    className="size-full object-cover"
                />
            </button>
        );
    }

    if (image?.status === 'failed' || (source && failedSource === source)) {
        return (
            <span
                className={cn(box, 'text-muted-foreground')}
                title={image?.error ?? 'The image preview could not be loaded'}
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
