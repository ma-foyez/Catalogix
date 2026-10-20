<?php

use App\Enums\DiscoveryStage;
use App\Enums\JobStatus;
use App\Enums\Platform;
use App\Enums\RouteSource;
use App\Jobs\DiscoverDomainJob;
use App\Jobs\ProcessPageJob;
use App\Models\Discovery;
use App\Models\Site;
use App\Models\User;
use App\Services\Discovery\DomainDiscoverer;
use App\Services\Discovery\RouteProber;
use App\Services\ExtractionService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Process::fake(['*' => Process::result(errorOutput: 'Playwright is not installed', exitCode: 3)]);
});

it('suggests scored product routes for a bare domain', function () {
    Http::fake([
        'chaldal.com/robots.txt' => Http::response("User-agent: *\nSitemap: https://chaldal.com/sitemap-index.xml\n", 200, ['Content-Type' => 'text/plain']),
        'chaldal.com/sitemap-index.xml' => Http::response('<?xml version="1.0"?><sitemapindex><sitemap><loc>https://chaldal.com/sitemap-categories.xml</loc></sitemap><sitemap><loc>https://chaldal.com/post-sitemap.xml</loc></sitemap></sitemapindex>', 200, ['Content-Type' => 'application/xml']),
        'chaldal.com/sitemap-categories.xml' => Http::response('<?xml version="1.0"?><urlset><url><loc>https://chaldal.com/category/snacks</loc></url></urlset>', 200, ['Content-Type' => 'application/xml']),
        'chaldal.com/sitemap.xml' => Http::response('', 404),
        'chaldal.com/sitemap_index.xml' => Http::response('', 404),
        'chaldal.com/' => Http::response(page('discovery-home.html')),
        'chaldal.com/popular' => Http::response(page('chaldal-popular.html')),
        'chaldal.com/fresh-vegetables' => Http::response(page('discovery-vegetables.html')),
        'chaldal.com/category/snacks' => Http::response('<html><body><h1>Snacks</h1><p>Coming soon</p></body></html>'),
    ]);

    $discovery = Discovery::factory()->for(Site::forHost('chaldal.com'))->create();
    app(DomainDiscoverer::class)->run($discovery);

    $discovery->refresh();
    $routes = $discovery->routes()->get()->keyBy('path');

    expect($discovery->status)->toBe(JobStatus::Done)
        ->and($routes['/popular']->score)->toBeGreaterThanOrEqual(60)
        ->and($routes['/popular']->is_selected)->toBeTrue()
        ->and($routes['/popular']->category_name)->toBe('Popular')
        ->and($routes['/popular']->sample_images)->toHaveCount(3)
        ->and($routes['/fresh-vegetables']->est_products)->toBe(120)
        ->and($routes['/fresh-vegetables']->category_name)->toBe('Fresh Vegetables')
        ->and($routes['/category/snacks']->source)->toBe(RouteSource::Sitemap)
        ->and($routes['/category/snacks']->score)->toBe(10)
        ->and($routes['/category/snacks']->is_selected)->toBeFalse()
        ->and($routes->has('/blog'))->toBeFalse()
        ->and($routes->has('/cart'))->toBeFalse()
        ->and($discovery->routes_found)->toBeGreaterThanOrEqual(3)
        ->and($discovery->stage)->toBe(DiscoveryStage::Finished)
        ->and($discovery->candidates)->toBeGreaterThanOrEqual(4)
        ->and($discovery->to_probe)->toBeGreaterThan(0)
        ->and($discovery->probed)->toBe($discovery->to_probe);

    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'facebook.com'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'post-sitemap'));
});

it('reads the WooCommerce category list with product counts', function () {
    Http::fake([
        'deshibazar.com.bd/robots.txt' => Http::response('', 404),
        'deshibazar.com.bd/sitemap*' => Http::response('', 404),
        'deshibazar.com.bd/' => Http::response(page('woo-category.html')),
        'deshibazar.com.bd/wp-json/wc/store/v1/products/categories*' => Http::response(page('woo-categories.json')),
        'deshibazar.com.bd/product-category/*' => Http::response(page('woo-category.html')),
    ]);

    $discovery = Discovery::factory()->for(Site::forHost('deshibazar.com.bd'))->create();
    app(DomainDiscoverer::class)->run($discovery);

    $routes = $discovery->routes()->get()->keyBy('category_name');

    expect($discovery->site->fresh()->platform)->toBe(Platform::WooCommerce)
        ->and($routes['Saree']->source)->toBe(RouteSource::PlatformApi)
        ->and($routes['Saree']->est_products)->toBe(48)
        ->and($routes['Saree']->score)->toBe(70)
        ->and($routes['Saree']->is_selected)->toBeTrue();
});

it('fails discovery for a site behind a bot challenge and marks it blocked', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        '*' => Http::response(page('cloudflare-challenge.html'), 403),
    ]);

    $discovery = Discovery::factory()->for(Site::forHost('example-shop.com.bd'))->create();
    app(DomainDiscoverer::class)->run($discovery);

    expect($discovery->fresh())
        ->status->toBe(JobStatus::Failed)
        ->error->toContain('Blocked')
        ->and($discovery->site->fresh()->is_blocked)->toBeTrue();
});

it('starts discovery from the web form', function () {
    Queue::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post(route('discoveries.store'), ['domains' => 'https://www.Chaldal.com/popular'])
        ->assertRedirect(route('discover'));

    expect(Discovery::first()->site->domain)->toBe('chaldal.com')
        ->and(Discovery::first()->auto_extract)->toBeTrue();
    Queue::assertPushed(DiscoverDomainJob::class, fn ($job) => $job->domain === 'chaldal.com');
});

it('starts every website in pasted text and skips one already being collected', function () {
    Queue::fake();
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('discoveries.store'), ['domains' => 'chaldal.com']);

    $this->actingAs($user)
        ->post(route('discoveries.store'), ['domains' => "https://bongofurniture.com, https://rflbd.com,\nhttps://www.smmbd.com/shop/ chaldal.com"])
        ->assertRedirect(route('discover'))
        ->assertSessionHasNoErrors();

    expect(Discovery::with('site')->get()->pluck('site.domain')->all())
        ->toBe(['chaldal.com', 'bongofurniture.com', 'rflbd.com', 'smmbd.com']);
    Queue::assertPushed(DiscoverDomainJob::class, 4);
});

it('names the part of the pasted text that is not a website', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('discoveries.store'), ['domains' => 'chaldal.com, hello'])
        ->assertSessionHasErrors(['domains' => '“hello” doesn\'t look like a website address. Try something like chaldal.com.']);

    expect(Discovery::count())->toBe(0);
});

it('extracts the recommended routes on its own after discovering a pasted website', function () {
    Http::fake([
        'chaldal.com/robots.txt' => Http::response('', 404),
        'chaldal.com/sitemap.xml' => Http::response('', 404),
        'chaldal.com/sitemap_index.xml' => Http::response('', 404),
        'chaldal.com/' => Http::response(page('discovery-home.html')),
        'chaldal.com/popular' => Http::response(page('chaldal-popular.html')),
        'chaldal.com/fresh-vegetables' => Http::response(page('discovery-vegetables.html')),
        'chaldal.com/*' => Http::response('<html><body><p>Nothing here</p></body></html>'),
    ]);
    Queue::fake();

    $discovery = Discovery::factory()->for(Site::forHost('chaldal.com'))->create(['auto_extract' => true]);
    (new DiscoverDomainJob($discovery->id, 'chaldal.com'))->handle(app(DomainDiscoverer::class), app(ExtractionService::class));

    $job = $discovery->fresh()->latestJob;
    $paths = $job->targets()->pluck('url')->map(fn ($url) => parse_url($url, PHP_URL_PATH))->sort()->values()->all();

    expect($paths)->toBe(['/fresh-vegetables', '/popular']);
    Queue::assertPushed(ProcessPageJob::class, 2);
});

it('rejects input that is not a domain', function () {
    $this->actingAs(User::factory()->create())
        ->post(route('discoveries.store'), ['domains' => 'hello world'])
        ->assertSessionHasErrors('domains');
});

it('ignores product counts hidden in scripts and on pages without products', function () {
    $prober = app(RouteProber::class);
    $json = '<script>window.__STATE__ = {"label": "590 items", "total": "15000 products"};</script>';
    $card = fn (int $i) => "<div class=\"product-card\"><img src=\"https://shop.test/p{$i}.jpg\"><a href=\"/p/{$i}\">Product {$i}</a><span class=\"price\">৳ ".(100 + $i).'</span></div>';
    $listing = '<html><body>'.$json.'<div class="cart"><b>Shipping: ৳ 59</b><i>0 items</i></div><p>Showing 1-4 of 132 results</p><div class="grid">'.implode('', array_map($card, range(1, 4))).'</div></body></html>';
    $landing = '<html><body>'.$json.'<h1>Meat &amp; Fish</h1><a href="/beef">Beef</a></body></html>';

    expect($prober->evaluate($listing, 'https://shop.test/snacks')->estProducts)->toBe(132)
        ->and($prober->evaluate($landing, 'https://shop.test/meat-fish')->estProducts)->toBe(0);
});
