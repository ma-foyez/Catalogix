<?php

use App\Enums\ExtractionStrategy;
use App\Enums\Platform;
use App\Models\Site;
use App\Services\Crawling\PageRenderer;
use App\Services\Extraction\ExtractionLadder;
use App\Services\Extraction\PageContext;
use App\Services\Extraction\Strategies\CardDetectionStrategy;
use App\Services\Extraction\Strategies\EmbeddedStateStrategy;
use App\Services\Extraction\Strategies\JsonLdStrategy;
use App\Services\Extraction\Strategies\NetworkJsonStrategy;
use App\Services\Extraction\Strategies\PlatformApiStrategy;
use App\Services\Extraction\Strategies\RecipeStrategy;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

function contextFor(string $url, string $html, ?Site $site = null, int $page = 1): PageContext
{
    $site ??= Site::forHost((string) parse_url($url, PHP_URL_HOST));

    return new PageContext($site, $url, $page, $html, null, app(PageRenderer::class));
}

/**
 * Fake the Python helper: write the given fixture to the --out file it was asked for.
 */
function fakeRenderer(string $fixture): void
{
    Process::fake(function (PendingProcess $process) use ($fixture) {
        $command = (array) $process->command;
        $out = $command[array_search('--out', $command, true) + 1];
        file_put_contents($out, page($fixture));

        return Process::result();
    });
}

beforeEach(function () {
    Http::fake(['*/robots.txt' => Http::response('', 404)]);
});

it('reads JSON-LD ItemList products (step 2)', function () {
    $result = app(JsonLdStrategy::class)->extract(contextFor('https://khaasfood.com/rice-dal', page('jsonld-itemlist.html')));

    expect($result->products)->toHaveCount(3)
        ->and($result->products[0]['name'])->toBe('Chinigura Aromatic Rice 1 kg')
        ->and($result->products[1]['prices'])->toBe(['135', '150']);
});

it('reads the WooCommerce Store API for a product category (step 3)', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'deshibazar.com.bd/wp-json/wc/store/v1/products/categories*' => Http::response(page('woo-categories.json')),
        'deshibazar.com.bd/wp-json/wc/store/v1/products?*' => Http::response(page('woo-products.json')),
    ]);

    $site = Site::forHost('deshibazar.com.bd');
    $site->update(['platform' => Platform::WooCommerce]);

    $result = app(PlatformApiStrategy::class)->extract(contextFor('https://deshibazar.com.bd/product-category/panjabi/', page('woo-category.html'), $site));

    expect($result->products)->toHaveCount(3)
        ->and($result->products[0]['price'])->toBe(1850.0)
        ->and($result->products[0]['regular_price'])->toBe(2200.0)
        ->and($result->hasMorePages)->toBeFalse();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'category=12') && str_contains($request->url(), 'page=1'));
});

it('reads Shopify products.json for a collection (step 3)', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'tangailtant.com/collections/sarees/products.json*' => Http::response(page('shopify-products.json')),
    ]);

    $site = Site::forHost('tangailtant.com');
    $site->update(['platform' => Platform::Shopify]);

    $result = app(PlatformApiStrategy::class)->extract(contextFor('https://tangailtant.com/collections/sarees', page('shopify-collection.html'), $site));

    expect($result->products)->toHaveCount(3)
        ->and($result->products[0]['url'])->toBe('https://tangailtant.com/products/jamdani-saree-red-gold')
        ->and($result->products[0]['regular_price'])->toBe('9500.00');
});

it('reads __NEXT_DATA__ embedded state (step 4)', function () {
    $result = app(EmbeddedStateStrategy::class)->extract(contextFor('https://gadgetbari.com.bd/category/mobile-phones', page('next-data.html')));

    expect($result->products)->toHaveCount(3)
        ->and($result->products[0]['name'])->toBe('Xiaomi Redmi Note 14 (8/256GB)')
        ->and($result->products[0]['regular_price'])->toBe(27999.0)
        ->and($result->products[0]['url'])->toBe('/product/redmi-note-14');
});

it('reads product JSON captured by the Python helper (step 5)', function () {
    fakeRenderer('rendered-network.json');

    $result = app(NetworkJsonStrategy::class)->extract(contextFor('https://bazarexpress.com.bd/groceries', page('spa-shell.html')));

    expect($result->products)->toHaveCount(3)
        ->and($result->usedRender)->toBeTrue()
        ->and($result->products[1]['name'])->toBe('Fresh Hilsha (Ilish) 1 kg')
        ->and($result->products[1]['price'])->toBe(1650.0);

    Process::assertRan(fn (PendingProcess $process) => in_array('--url', (array) $process->command, true));
});

it('does not launch the browser when the static page already shows product cards', function () {
    $result = app(NetworkJsonStrategy::class)->extract(contextFor('https://chaldal.com/popular', page('chaldal-popular.html')));

    expect($result->products)->toBe([]);
    Process::assertNothingRan();
});

it('detects repeated product cards without links on chaldal.com/popular (step 6)', function () {
    $result = app(CardDetectionStrategy::class)->extract(contextFor('https://chaldal.com/popular', page('chaldal-popular.html')));

    expect($result->products)->toHaveCount(6)
        ->and($result->recipe['card'])->toBe('div.productV2Catalog')
        ->and($result->products[0]['name'])->toBe('Tata Tea Premium')
        ->and($result->products[0]['prices'])->toBe([149.0, 230.0])
        ->and($result->products[2]['image'])->toBe('https://i.chaldn.com/_mpimage/teer-soybean-oil-5-ltr?q=best&v=1&m=400');
});

it('applies a saved recipe (step 1)', function () {
    $site = Site::forHost('chaldal.com');
    $site->update(['selectors' => ['card' => 'div.productV2Catalog', 'name' => 'div.pvName', 'price' => 'div.productV2discountedPrice', 'image' => 'img'], 'recipe_valid' => true]);

    $result = app(RecipeStrategy::class)->extract(contextFor('https://chaldal.com/popular', page('chaldal-popular.html'), $site));

    expect($result->products)->toHaveCount(6)
        ->and($result->products[3]['name'])->toBe('Deshi Peyaj (Onion)');
});

it('runs the ladder in order and stops at the first strategy with three priced products', function () {
    Process::fake();

    $outcome = app(ExtractionLadder::class)->run(contextFor('https://chaldal.com/popular', page('chaldal-popular.html')));

    expect($outcome->strategy)->toBe(ExtractionStrategy::CardDetection)
        ->and($outcome->products)->toHaveCount(6)
        ->and(collect($outcome->products)->firstWhere('name', 'Deshi Peyaj (Onion)')['price'])->toBe(90.0);
});

it('tries the site\'s saved strategy first', function () {
    $site = Site::forHost('khaasfood.com');
    $site->update(['strategy' => ExtractionStrategy::JsonLd]);

    $outcome = app(ExtractionLadder::class)->run(contextFor('https://khaasfood.com/rice-dal', page('jsonld-itemlist.html'), $site));

    expect($outcome->strategy)->toBe(ExtractionStrategy::JsonLd);
});

it('flags a saved recipe that suddenly finds nothing', function () {
    $site = Site::forHost('chaldal.com');
    $site->update(['selectors' => ['card' => 'div.old-card-layout'], 'recipe_valid' => true]);

    app(ExtractionLadder::class)->run(contextFor('https://chaldal.com/popular', page('chaldal-popular.html'), $site));

    expect($site->fresh()->recipe_valid)->toBeFalse();
});
