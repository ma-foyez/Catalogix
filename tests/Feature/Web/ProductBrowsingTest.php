<?php

use App\Enums\ImageStatus;
use App\Models\Category;
use App\Models\Export;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\Exports\ExportProductQuery;
use App\Services\Extraction\ProductNormalizer;
use App\Services\Extraction\ProductUpserter;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    Storage::fake('public');
});

function browsingProducts(): array
{
    $target = makeTarget('https://shop.test/collections/chairs', 'Chairs');
    $normalizer = app(ProductNormalizer::class);
    $items = array_map(fn ($i) => $normalizer->normalize([
        'name' => "Office Chair {$i}", 'price' => 2000, 'brand' => 'Bongo Furniture', 'sku' => "CH-{$i}",
        'url' => "https://shop.test/products/chair-{$i}", 'image' => "https://shop.test/images/chair-{$i}.jpg",
    ], $target->url), range(1, 3));
    app(ProductUpserter::class)->save($target, $target->site(), $items);
    $target->scrapeJob->refreshTotals();

    return [$target, Product::with('image')->orderBy('id')->get()];
}

it('returns product details and source links without duplicate rows or category counts', function () {
    [$target, $products] = browsingProducts();
    $other = $target->scrapeJob->targets()->create(['url' => 'https://shop.test/collections/all', 'status' => 'done', 'products_found' => 999]);
    $other->products()->attach($products->pluck('id'));

    $this->actingAs($target->scrapeJob->user)->get(route('jobs.show', $target->scrapeJob))->assertInertia(fn (Assert $page) => $page
        ->where('products.meta.total', 3)
        ->where('products.data.0.brand', 'Bongo Furniture')
        ->where('products.data.0.category', 'Chairs')
        ->where('products.data.0.website_url', 'https://shop.test')
        ->where('products.data.0.sources.0.url', $target->url)
        ->has('products.data.0.sources', 2)
        ->where('categories.0.count', 3)
        ->where('job.routes.1.products_found', 3));
});

it('counts and filters categories within the selected website collection', function () {
    [$target, $products] = browsingProducts();
    $other = $target->scrapeJob->targets()->create(['url' => 'https://shop.test/collections/home', 'status' => 'done']);
    $other->products()->attach($products->take(2)->pluck('id'));
    $this->actingAs($target->scrapeJob->user)
        ->get(route('jobs.show', ['job' => $target->scrapeJob, 'route' => $other->id, 'search' => 'Chair 1']))
        ->assertInertia(fn (Assert $page) => $page->where('products.meta.total', 1)->where('categories.0.count', 1));
});

it('includes uncategorized products in category counts and filtered exports', function () {
    [$target, $products] = browsingProducts();
    $products->first()->forceFill(['category_id' => null])->save();
    $this->actingAs($target->scrapeJob->user)
        ->get(route('jobs.show', ['job' => $target->scrapeJob, 'category' => 'uncategorized']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('products.meta.total', 1)->where('categories.0.name', 'Uncategorized')->where('categories.0.count', 1));
    $this->postJson(route('jobs.excel', $target->scrapeJob), ['filters' => ['category' => 'uncategorized']])->assertAccepted();
    $export = Export::sole();
    expect(app(ExportProductQuery::class)->for($export)->pluck('products.id')->all())->toBe([$products->first()->id]);
});

it('downloads a saved image as an attachment and protects other users images', function () {
    [$target, $products] = browsingProducts();
    $image = $products->first()->image;
    $image->forceFill(['status' => ImageStatus::Downloaded, 'local_path' => 'products/shop.test/chair.webp'])->save();
    Storage::disk('public')->put($image->local_path, 'saved-image-content');

    $this->actingAs($target->scrapeJob->user)->get(route('images.download', $image))
        ->assertOk()->assertDownload('office-chair-1-'.$products->first()->id.'.webp')
        ->assertStreamedContent('saved-image-content');
    $this->actingAs(User::factory()->create())->get(route('images.download', $image))->assertForbidden();
});

it('does not download pending or missing local pictures', function () {
    [$target, $products] = browsingProducts();
    $image = $products->first()->image;
    $this->actingAs($target->scrapeJob->user)->get(route('images.download', $image))->assertStatus(409);
    $image->forceFill(['status' => ImageStatus::Downloaded, 'local_path' => 'missing.webp'])->save();
    $this->get(route('images.download', $image))->assertNotFound();
});

it('never lets an all-products collection overwrite a specific product category', function () {
    [$target, $products] = browsingProducts();
    $general = $target->scrapeJob->targets()->create(['url' => 'https://shop.test/collections/all-products', 'status' => 'queued']);
    $item = app(ProductNormalizer::class)->normalize(['name' => 'Office Chair 1', 'price' => 2000, 'url' => 'https://shop.test/products/chair-1'], $general->url);
    app(ProductUpserter::class)->save($general, $general->site(), [$item]);

    expect($products->first()->fresh()->category->name)->toBe('Chairs')
        ->and(ProductImage::count())->toBe(3)
        ->and(Product::count())->toBe(3);
});

it('repairs existing broad labels using known specific collections and retains saved pictures', function () {
    [$target, $products] = browsingProducts();
    $broad = Category::resolve($target->site(), 'All Products', 'https://shop.test/collections/all-products');
    Product::query()->update(['category_id' => $broad->id]);
    $imagesBefore = ProductImage::pluck('source_url', 'id')->all();
    $this->artisan('catalogix:repair-categories --site=shop.test --dry-run')->expectsOutput('Would repair 3 product categories.')->assertSuccessful();
    expect(Product::first()->category_id)->toBe($broad->id);
    $this->artisan('catalogix:repair-categories --site=shop.test')->expectsOutput('Repaired 3 product categories.')->assertSuccessful();
    expect(Product::with('category')->get()->pluck('category.name')->unique()->all())->toBe(['Chairs'])
        ->and(ProductImage::pluck('source_url', 'id')->all())->toBe($imagesBefore);
    $this->artisan('catalogix:repair-categories --site=shop.test')->expectsOutput('Repaired 0 product categories.')->assertSuccessful();
});
