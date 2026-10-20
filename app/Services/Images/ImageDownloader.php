<?php

namespace App\Services\Images;

use App\Enums\ImageStatus;
use App\Exceptions\ImageDownloadException;
use App\Models\ProductImage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Encoders\JpegEncoder;
use Intervention\Image\Encoders\WebpEncoder;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Downloads one product image, validates it, and stores a WebP original plus a 200 px JPEG thumbnail:
 * products/{domain}/{category}/{product-slug}.webp and {product-slug}_thumb.jpg on the public disk.
 * An image URL already downloaded for another product is reused instead of fetched again.
 */
final class ImageDownloader
{
    public function __construct(private readonly ImageManager $images) {}

    /**
     * @throws ImageDownloadException
     */
    public function download(ProductImage $image): ProductImage
    {
        if ($image->status === ImageStatus::Downloaded) {
            return $image;
        }

        if ($this->reuseExisting($image)) {
            return $image;
        }

        $image->increment('attempts');
        $body = $this->fetch($image->source_url);

        try {
            $decoded = $this->images->decodeBinary($body);
        } catch (Throwable) {
            throw ImageDownloadException::permanent('The file could not be read as an image.');
        }

        $minimum = (int) config('catalogix.images.min_dimension', 40);

        if ($decoded->width() < $minimum || $decoded->height() < $minimum) {
            throw ImageDownloadException::permanent("Image is too small ({$decoded->width()}×{$decoded->height()}), probably a placeholder.");
        }

        $width = $decoded->width();
        $height = $decoded->height();
        [$path, $thumbPath] = $this->paths($image);

        $webp = (string) $decoded->encode(new WebpEncoder(quality: 82))->toString();
        $thumb = (string) $decoded->scaleDown(width: (int) config('catalogix.images.thumb_width', 200))
            ->encode(new JpegEncoder(quality: 80))
            ->toString();

        $this->disk()->put($path, $webp);
        $this->disk()->put($thumbPath, $thumb);

        $image->forceFill([
            'local_path' => $path,
            'thumb_path' => $thumbPath,
            'width' => $width,
            'height' => $height,
            'bytes' => strlen($webp),
            'status' => ImageStatus::Downloaded,
            'error' => null,
        ])->save();

        return $image;
    }

    public function markFailed(ProductImage $image, string $reason): void
    {
        $image->forceFill(['status' => ImageStatus::Failed, 'error' => mb_substr($reason, 0, 500)])->save();
    }

    private function reuseExisting(ProductImage $image): bool
    {
        $twin = ProductImage::query()
            ->where('url_hash', $image->url_hash)
            ->where('status', ImageStatus::Downloaded)
            ->whereKeyNot($image->id)
            ->first();

        if ($twin === null || ! $this->disk()->exists((string) $twin->local_path)) {
            return false;
        }

        $image->forceFill([
            'local_path' => $twin->local_path,
            'thumb_path' => $twin->thumb_path,
            'width' => $twin->width,
            'height' => $twin->height,
            'bytes' => $twin->bytes,
            'status' => ImageStatus::Downloaded,
            'error' => null,
        ])->save();

        return true;
    }

    /**
     * @throws ImageDownloadException
     */
    private function fetch(string $url): string
    {
        try {
            $response = Http::withUserAgent((string) config('catalogix.user_agent'))
                ->accept('image/avif,image/webp,image/png,image/jpeg,image/*;q=0.8')
                ->timeout(20)
                ->get($url);
        } catch (ConnectionException $e) {
            throw ImageDownloadException::retryable('Could not connect: '.$e->getMessage());
        }

        if ($response->serverError() || $response->status() === 429) {
            throw ImageDownloadException::retryable("The image server answered HTTP {$response->status()}.");
        }

        if (! $response->successful()) {
            throw ImageDownloadException::permanent("The image server answered HTTP {$response->status()}.");
        }

        $type = strtolower($response->header('Content-Type'));

        if ($type !== '' && ! str_starts_with($type, 'image/')) {
            throw ImageDownloadException::permanent("Not an image (content type {$type}).");
        }

        $body = $response->body();
        $bytes = strlen($body);

        if ($bytes < (int) config('catalogix.images.min_bytes', 1024)) {
            throw ImageDownloadException::permanent("Image is only {$bytes} bytes, probably a tracking pixel or placeholder.");
        }

        if ($bytes > (int) config('catalogix.images.max_bytes', 15 * 1024 * 1024)) {
            throw ImageDownloadException::permanent('Image is larger than the download limit.');
        }

        return $body;
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function paths(ProductImage $image): array
    {
        $product = $image->product()->with(['site', 'category'])->firstOrFail();

        $directory = 'products/'.$product->site->domain.'/'.($product->category?->slug ?: 'uncategorized');
        $slug = Str::limit(Str::slug($product->name), 80, '') ?: 'product';

        // A different product with the same name already owns this file name.
        $taken = ProductImage::query()
            ->where('local_path', "{$directory}/{$slug}.webp")
            ->where('url_hash', '!=', $image->url_hash)
            ->exists();

        if ($taken) {
            $slug .= '-'.$product->id;
        }

        return ["{$directory}/{$slug}.webp", "{$directory}/{$slug}_thumb.jpg"];
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('catalogix.images.disk', 'public'));
    }
}
