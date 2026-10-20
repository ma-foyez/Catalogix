<?php

use App\Enums\ExtractionStrategy;
use App\Enums\ImageStatus;
use App\Enums\JobStatus;
use App\Enums\TargetStatus;
use App\Jobs\DownloadImageJob;
use App\Jobs\ProcessPageJob;
use App\Models\Product;
use App\Models\ProductImage;
use App\Services\ExtractionService;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    Process::fake();
});

it('extracts chaldal.com/popular with name, price and image and queues image downloads', function () {
    Http::fake([
        'chaldal.com/robots.txt' => Http::response("# content signals only\n", 200, ['Content-Type' => 'text/plain']),
        'chaldal.com/popular' => Http::response(page('chaldal-popular.html')),
    ]);

    $target = makeTarget('https://chaldal.com/popular', 'Popular');
    runPageJobs();

    $target->refresh();

    expect($target->status)->toBe(TargetStatus::Done)
        ->and($target->strategy_used)->toBe(ExtractionStrategy::CardDetection)
        ->and($target->products_found)->toBe(6)
        ->and($target->scrapeJob->fresh()->status)->toBe(JobStatus::Done);

    $tea = Product::where('name', 'Tata Tea Premium')->firstOrFail();

    expect((float) $tea->price)->toBe(230.0)
        ->and((float) $tea->sale_price)->toBe(149.0)
        ->and($tea->category->name)->toBe('Popular')
        ->and($tea->image->source_url)->toStartWith('https://i.chaldn.com/_mpimage/tata-tea-premium');

    $site = $target->site()->fresh();

    expect($site->strategy)->toBe(ExtractionStrategy::CardDetection)
        ->and($site->selectors['card'])->toBe('div.productV2Catalog')
        ->and($site->recipe_valid)->toBeTrue();

    Queue::assertPushed(DownloadImageJob::class, 6);
});

it('follows rel=next and ?page=N pagination and stops when a page adds nothing new', function () {
    Http::fake([
        'v2.sawdahexpress.com.bd/robots.txt' => Http::response('', 404),
        'v2.sawdahexpress.com.bd/category/shoes' => Http::response(page('shop-page-1.html')),
        'v2.sawdahexpress.com.bd/category/shoes?page=2' => Http::response(page('shop-page-2.html')),
        'v2.sawdahexpress.com.bd/category/shoes?page=3' => Http::response(page('shop-page-2.html')),
    ]);

    $target = makeTarget('https://v2.sawdahexpress.com.bd/category/shoes', 'Shoes');
    runPageJobs();

    $target->refresh();

    // Page 3 (?page=3) repeats page 2, adds no new product and ends the route.
    expect($target->status)->toBe(TargetStatus::Done)
        ->and($target->pages_crawled)->toBe(3)
        ->and($target->products_found)->toBe(6)
        ->and(Product::count())->toBe(6);

    $bata = Product::where('name', "Bata Men's Formal Shoe")->firstOrFail();

    expect($bata->product_url)->toBe('https://v2.sawdahexpress.com.bd/product/bata-mens-formal-shoe')
        ->and((float) $bata->price)->toBe(3499.0)
        ->and((float) $bata->sale_price)->toBe(2999.0)
        ->and($bata->in_stock)->toBeTrue();

    expect(Product::where('name', 'Lotto Running Shoe')->first())
        ->in_stock->toBeFalse()
        ->price->toEqual('3200.00');

    expect(ProductImage::where('source_url', 'https://v2.sawdahexpress.com.bd/uploads/apex-800.jpg')->exists())->toBeTrue();
});

it('updates products on a re-run instead of duplicating them', function () {
    Http::fake([
        'chaldal.com/robots.txt' => Http::response('', 404),
        'chaldal.com/popular' => Http::response(page('chaldal-popular.html')),
    ]);

    $target = makeTarget('https://chaldal.com/popular', 'Popular');
    runPageJobs();

    $rerun = app(ExtractionService::class)->rerun($target->scrapeJob);
    runPageJobs();

    expect(Product::count())->toBe(6)
        ->and(ProductImage::count())->toBe(6)
        ->and($rerun->targets()->first()->products_found)->toBe(6)
        ->and($rerun->fresh()->total_products)->toBe(6);
});

it('marks a Cloudflare challenge as blocked and never retries around it', function () {
    Http::fake([
        'www.example-shop.com.bd/robots.txt' => Http::response('', 404),
        'www.example-shop.com.bd/*' => Http::response(page('cloudflare-challenge.html'), 403),
    ]);

    $target = makeTarget('https://www.example-shop.com.bd/category/phones');
    runPageJobs();

    $target->refresh();

    expect($target->status)->toBe(TargetStatus::Blocked)
        ->and($target->error)->toContain('bot challenge')
        ->and($target->site()->is_blocked)->toBeTrue()
        ->and(Product::count())->toBe(0);

    Http::assertSentCount(2);
});

it('respects robots.txt and marks disallowed routes blocked', function () {
    Http::fake([
        'dokan.com.bd/robots.txt' => Http::response("User-agent: *\nDisallow: /private/\n", 200, ['Content-Type' => 'text/plain']),
    ]);

    $target = makeTarget('https://dokan.com.bd/private/deals');
    runPageJobs();

    expect($target->fresh())
        ->status->toBe(TargetStatus::Blocked)
        ->error->toContain('robots.txt');

    Http::assertNotSent(fn ($request) => str_contains($request->url(), '/private/'));
});

it('fails a route with a clear hint when no strategy finds products', function () {
    Http::fake([
        'bazarexpress.com.bd/robots.txt' => Http::response('', 404),
        'bazarexpress.com.bd/groceries' => Http::response(page('spa-shell.html')),
    ]);

    Process::fake(['*' => Process::result(errorOutput: 'Playwright is not installed', exitCode: 3)]);

    $target = makeTarget('https://bazarexpress.com.bd/groceries');
    runPageJobs();

    expect($target->fresh())
        ->status->toBe(TargetStatus::Failed)
        ->error->toContain('visual recipe picker')
        ->error->toContain('Playwright is not installed');
});

it('dispatches page jobs on the pages queue with one-domain-at-a-time middleware', function () {
    $job = new ProcessPageJob(1, 'https://chaldal.com/popular');

    expect($job->queue)->toBe('pages')
        ->and($job->middleware()[0])->toBeInstanceOf(WithoutOverlapping::class)
        ->and($job->timeout)->toBe(180);
});

it('keeps image status pending until the image job runs', function () {
    Http::fake([
        'chaldal.com/robots.txt' => Http::response('', 404),
        'chaldal.com/popular' => Http::response(page('chaldal-popular.html')),
    ]);

    makeTarget('https://chaldal.com/popular');
    runPageJobs();

    expect(ProductImage::where('status', ImageStatus::Pending)->count())->toBe(6);
});
