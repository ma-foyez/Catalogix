<?php

use App\Models\Category;
use App\Models\Product;
use App\Models\Site;
use App\Services\Extraction\ProductNormalizer;
use App\Services\Extraction\ProductUpserter;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
});

function saveProducts(string $url, ?string $category, array $raw): void
{
    $target = makeTarget($url, $category);
    $normalizer = app(ProductNormalizer::class);
    $products = array_values(array_filter(array_map(fn (array $item) => $normalizer->normalize($item, $url), $raw)));

    app(ProductUpserter::class)->save($target, Site::forHost((string) parse_url($url, PHP_URL_HOST)), $products);
}

it('splits a nested category page into category and subcategory and keeps the description', function () {
    saveProducts('https://shop.test/product-category/furniture/chairs/', 'Chairs', [[
        'name' => 'Budget Chair Gem',
        'price' => '575',
        'url' => 'https://shop.test/product/budget-chair-gem/',
        'sku' => 'BC-01',
        'description' => '<p>Stackable plastic chair for <strong>home</strong> and office.</p>',
    ]]);

    $product = Product::with('category')->sole();

    expect($product->category->name)->toBe('Furniture')
        ->and($product->subcategory)->toBe('Chairs')
        ->and($product->sku)->toBe('BC-01')
        ->and($product->description)->toBe('Stackable plastic chair for home and office.');
});

it('uses the product\'s own category as subcategory on a one-level page', function () {
    saveProducts('https://shop.test/collections/kitchen', 'Kitchen', [[
        'name' => 'Steel Kettle', 'price' => '1200', 'url' => 'https://shop.test/products/kettle', 'category' => 'Kettles',
    ]]);

    $product = Product::with('category')->sole();

    expect($product->category->name)->toBe('Kitchen')
        ->and($product->subcategory)->toBe('Kettles');
});

it('never relabels products as "Home" when the home page lists them too', function () {
    $item = ['name' => 'Family Almira 4D', 'price' => '8800', 'url' => 'https://shop.test/product/family-almira-4d/'];

    saveProducts('https://shop.test/category/storage/', 'Storage', [$item]);
    saveProducts('https://shop.test/', 'Home', [$item]);

    expect(Product::with('category')->sole()->category->name)->toBe('Storage');
});

it('gives home-page-only products their own category', function () {
    saveProducts('https://shop.test/', 'Home', [[
        'name' => 'Handy Stool', 'price' => '260', 'url' => 'https://shop.test/product/handy-stool/', 'category' => 'Stools',
    ]]);

    expect(Product::with('category')->sole()->category->name)->toBe('Stools');
});

it('clears the old "Home" label when the home page is collected again', function () {
    $item = ['name' => 'Handy Stool', 'price' => '260', 'url' => 'https://shop.test/product/handy-stool/'];
    saveProducts('https://shop.test/', 'Home', [$item]);
    Product::query()->update(['category_id' => Category::resolve(Site::forHost('shop.test'), 'Home')->id]);

    saveProducts('https://shop.test/', 'Home', [$item]);

    expect(Product::sole()->category_id)->toBeNull();
});
