<?php

use App\Enums\ImageStatus;
use App\Jobs\DownloadImageJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\Site;
use App\Services\ExtractionService;
use App\Services\Images\ImageDownloader;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

function productWithImage(string $name, string $imageUrl, string $category = 'Popular'): ProductImage
{
    $site = Site::forHost('chaldal.com');
    $product = Product::create([
        'site_id' => $site->id,
        'category_id' => Category::resolve($site, $category)->id,
        'hash' => sha1($name),
        'name' => $name,
        'price' => 149,
    ]);

    return ProductImage::create(['product_id' => $product->id, 'source_url' => $imageUrl, 'url_hash' => sha1($imageUrl), 'status' => ImageStatus::Pending]);
}

beforeEach(function () {
    Storage::fake('public');
});

it('stores a WebP original and a 200 px JPEG thumbnail per product', function () {
    Http::fake(['i.chaldn.com/*' => Http::response(pngImage(600, 600), 200, ['Content-Type' => 'image/png'])]);

    $image = productWithImage('Tata Tea Premium', 'https://i.chaldn.com/_mpimage/tata-tea-premium-400-gm');
    (new DownloadImageJob($image->id, 'chaldal.com'))->handle(app(ImageDownloader::class));

    $image->refresh();

    expect($image->status)->toBe(ImageStatus::Downloaded)
        ->and($image->local_path)->toBe('products/chaldal.com/popular/tata-tea-premium.webp')
        ->and($image->thumb_path)->toBe('products/chaldal.com/popular/tata-tea-premium_thumb.jpg')
        ->and($image->width)->toBe(600);

    Storage::disk('public')->assertExists($image->local_path);
    Storage::disk('public')->assertExists($image->thumb_path);

    $thumb = getimagesizefromstring(Storage::disk('public')->get($image->thumb_path));
    expect($thumb[0])->toBe(200)->and($thumb['mime'])->toBe('image/jpeg');
});

it('never downloads the same image URL twice', function () {
    Http::fake(['i.chaldn.com/*' => Http::response(pngImage(), 200, ['Content-Type' => 'image/png'])]);

    $first = productWithImage('Miniket Rice 5 kg', 'https://i.chaldn.com/_mpimage/rice');
    $second = productWithImage('Miniket Rice 5 kg (Offer pack)', 'https://i.chaldn.com/_mpimage/rice', 'Offers');

    $downloader = app(ImageDownloader::class);
    $downloader->download($first);
    $downloader->download($second->refresh());

    expect($second->fresh()->local_path)->toBe($first->fresh()->local_path);
    Http::assertSentCount(1);
});

it('rejects tracking pixels, placeholders and non-images with a reason', function (string $body, string $type, string $reason) {
    Http::fake(['cdn.shop.com.bd/*' => Http::response($body, 200, ['Content-Type' => $type])]);

    $image = productWithImage('Apex Sneaker', 'https://cdn.shop.com.bd/apex.jpg');
    (new DownloadImageJob($image->id, 'chaldal.com'))->handle(app(ImageDownloader::class));

    expect($image->fresh())
        ->status->toBe(ImageStatus::Failed)
        ->error->toContain($reason);
})->with([
    'tracking pixel' => [base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'), 'image/gif', 'tracking pixel'],
    'tiny placeholder' => [fn () => pngImage(20, 20).str_repeat("\0", 2000), 'image/png', 'too small'],
    'html error page' => [str_repeat('<p>Not found</p>', 200), 'text/html', 'Not an image'],
]);

it('retries failed images on request', function () {
    Queue::fake();

    $target = makeTarget('https://chaldal.com/popular');
    $image = productWithImage('Teer Soybean Oil', 'https://i.chaldn.com/_mpimage/teer');
    $target->products()->attach($image->product_id);
    $image->update(['status' => ImageStatus::Failed, 'error' => 'HTTP 503']);

    expect(app(ExtractionService::class)->retryImages($target->scrapeJob))->toBe(1)
        ->and($image->fresh()->status)->toBe(ImageStatus::Pending);

    Queue::assertPushed(DownloadImageJob::class, fn ($job) => $job->imageId === $image->id);
});
