<?php

use App\Enums\ImageStatus;
use App\Enums\JobStatus;
use App\Enums\TargetStatus;
use App\Jobs\DiscoverDomainJob;
use App\Jobs\DownloadImageJob;
use App\Jobs\ProcessPageJob;
use App\Models\Discovery;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\User;
use App\Services\Discovery\DomainDiscoverer;
use App\Services\Extraction\ProductNormalizer;
use App\Services\Extraction\ProductUpserter;
use App\Services\ExtractionService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
    Process::fake();
});

it('stores a per-website cap and inherits it when extraction starts', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('discoveries.store'), [
        'domains' => 'chaldal.com, rflbd.com', 'max_products' => 50,
    ])->assertSessionHasNoErrors();

    expect(Discovery::pluck('max_products')->all())->toBe([50, 50]);

    $target = makeTarget('https://chaldal.com/popular');
    $target->scrapeJob->discovery->forceFill(['max_products' => 50])->save();
    $job = app(ExtractionService::class)->startRecommended($target->scrapeJob->discovery);
    expect($job->max_products)->toBe(50);
});

it('rejects invalid product limits', function ($limit) {
    $this->actingAs(User::factory()->create())->post(route('discoveries.store'), [
        'domains' => 'chaldal.com', 'max_products' => $limit,
    ])->assertSessionHasErrors('max_products');
    expect(Discovery::count())->toBe(0);
})->with([0, -1, 100001, 'abc', 1.5]);

it('stops exactly at the cap inside a page and skips the remaining sections and pagination', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'v2.sawdahexpress.com.bd/category/shoes' => Http::response(page('shop-page-1.html')),
    ]);
    $target = makeTarget('https://v2.sawdahexpress.com.bd/category/shoes', 'Shoes');
    $job = $target->scrapeJob;
    $job->forceFill(['max_products' => 2])->save();
    $other = $job->targets()->create(['url' => 'https://v2.sawdahexpress.com.bd/category/other', 'status' => TargetStatus::Queued]);
    ProcessPageJob::dispatch($other->id, $other->url);

    runPageJobs();

    expect(Product::count())->toBe(2)
        ->and(ProductImage::count())->toBe(2)
        ->and($job->fresh()->total_products)->toBe(2)
        ->and($job->fresh()->status)->toBe(JobStatus::Done)
        ->and($other->fresh()->status)->toBe(TargetStatus::Done);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'page=2') || str_contains($request->url(), '/other'));
    Queue::assertPushed(DownloadImageJob::class, 2);
});

it('shares the unique product budget across routes including duplicate products', function () {
    $target = makeTarget('https://shop.test/category/one', 'One');
    $job = $target->scrapeJob;
    $job->forceFill(['max_products' => 3])->save();
    $other = $job->targets()->create(['url' => 'https://shop.test/category/two', 'status' => TargetStatus::Queued]);
    $normalizer = app(ProductNormalizer::class);
    $products = array_map(fn ($i) => $normalizer->normalize(['name' => "Product {$i}", 'price' => 10, 'url' => "https://shop.test/product/{$i}"], $target->url), range(1, 5));
    $upserter = app(ProductUpserter::class);
    $upserter->save($target, $target->site(), array_slice($products, 0, 2));
    $upserter->save($other, $other->site(), [$products[0], $products[0], ...array_slice($products, 2)]);
    $job->refreshTotals();

    expect(Product::count())->toBe(3)
        ->and($job->total_products)->toBe(3)
        ->and($other->products()->count())->toBe(2);
});

it('holds a paused page without fetching and resumes the same queued page', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'chaldal.com/popular' => Http::response(page('chaldal-popular.html')),
    ]);
    $target = makeTarget('https://chaldal.com/popular');
    $discovery = $target->scrapeJob->discovery;
    $this->actingAs($discovery->user)->post(route('discoveries.pause', $discovery))->assertRedirect();
    $payload = Queue::pushed(ProcessPageJob::class)->first();
    app()->call([$payload, 'handle']);
    Http::assertNothingSent();
    expect(Product::count())->toBe(0);

    $this->post(route('discoveries.resume', $discovery))->assertRedirect();
    app()->call([$payload, 'handle']);
    expect(Product::count())->toBe(6);
});

it('keeps pagination progress when pausing between pages', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'v2.sawdahexpress.com.bd/category/shoes' => Http::response(page('shop-page-1.html')),
        'v2.sawdahexpress.com.bd/category/shoes?page=2' => Http::response(page('shop-page-2.html')),
    ]);
    $target = makeTarget('https://v2.sawdahexpress.com.bd/category/shoes', 'Shoes');
    app()->call([Queue::pushed(ProcessPageJob::class)->first(), 'handle']);
    $next = Queue::pushed(ProcessPageJob::class)->last();
    expect($next->page)->toBe(2);
    $this->actingAs($target->scrapeJob->user)->post(route('jobs.pause', $target->scrapeJob));
    $before = Http::recorded()->count();
    app()->call([$next, 'handle']);
    expect(Http::recorded()->count())->toBe($before)
        ->and($target->fresh()->pages_crawled)->toBe(1);

    $this->post(route('jobs.resume', $target->scrapeJob));
    app()->call([$next, 'handle']);
    expect($target->fresh()->pages_crawled)->toBe(2)
        ->and($target->scrapeJob->fresh()->total_products)->toBe(6);
});

it('releases a long-paused database job beyond 255 attempts and then processes it after resume', function () {
    config(['queue.default' => 'database']);
    Queue::fake()->except([ProcessPageJob::class]);
    app('queue.worker')->setManager(Queue::getFacadeRoot()->queue);
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'chaldal.com/popular' => Http::response(page('chaldal-popular.html')),
    ]);
    $target = makeTarget('https://chaldal.com/popular');
    $target->scrapeJob->forceFill(['paused_at' => now()->subDays(3)])->save();
    DB::table('jobs')->update(['attempts' => 300]);

    $this->artisan('queue:work --once --queue=pages --sleep=0 --tries=1')->assertSuccessful();
    Http::assertNothingSent();
    expect(DB::table('jobs')->value('attempts'))->toBe(301);

    $this->actingAs($target->scrapeJob->user)->post(route('jobs.resume', $target->scrapeJob));
    DB::table('jobs')->update(['available_at' => now()->getTimestamp()]);
    $this->artisan('queue:work --once --queue=pages --sleep=0 --tries=1')->assertSuccessful();
    expect($target->scrapeJob->fresh()->total_products)->toBe(6)
        ->and(DB::table('jobs')->count())->toBe(0);
});

it('pauses an in-progress discovery between requests and resumes safely', function () {
    $discovery = Discovery::factory()->create(['auto_extract' => true]);
    $host = $discovery->site->domain;
    Http::fake(function ($request) use ($discovery) {
        if (str_ends_with($request->url(), '/robots.txt')) {
            return Http::response('', 404);
        }
        $discovery->forceFill(['paused_at' => now()])->save();

        return Http::response(page('discovery-home.html'));
    });

    (new DiscoverDomainJob($discovery->id, $host))->handle(app(DomainDiscoverer::class), app(ExtractionService::class));
    expect($discovery->fresh()->paused_at)->not->toBeNull()
        ->and($discovery->fresh()->status)->toBe(JobStatus::Running);
    Queue::assertNotPushed(ProcessPageJob::class);
    Http::assertSentCount(2);
});

it('holds pending images when their collection is paused', function () {
    Http::fake([
        '*/robots.txt' => Http::response('', 404),
        'chaldal.com/popular' => Http::response(page('chaldal-popular.html')),
    ]);
    $target = makeTarget('https://chaldal.com/popular');
    runPageJobs();
    $this->actingAs($target->scrapeJob->user)->post(route('jobs.pause', $target->scrapeJob));
    $before = Http::recorded()->count();
    runImageJobs();
    expect(Http::recorded()->count())->toBe($before)
        ->and(ProductImage::where('status', ImageStatus::Pending)->count())->toBe(6)
        ->and(ProductImage::sum('attempts'))->toBe(0);
});

it('prevents another user from pausing or resuming collections', function () {
    $target = makeTarget('https://shop.test/category/one');
    $this->actingAs(User::factory()->create());
    foreach (['pause', 'resume'] as $action) {
        $this->post(route("discoveries.{$action}", $target->scrapeJob->discovery))->assertForbidden();
        $this->postJson(route("api.jobs.{$action}", $target->scrapeJob))->assertForbidden();
    }
});

it('shows a paused site and resumes a job paused from its product page', function () {
    $target = makeTarget('https://shop.test/category/one');
    $job = $target->scrapeJob;
    $job->discovery->forceFill(['status' => JobStatus::Done])->save();
    $this->actingAs($job->user)->postJson(route('api.jobs.pause', $job))->assertJson(['paused' => true]);
    $this->get(route('discover'))->assertInertia(fn (Assert $page) => $page
        ->where('runs.data.0.phase', 'paused')->where('runs.data.0.paused', true));
    $this->postJson(route('api.discoveries.resume', $job->discovery))->assertJson(['paused' => false]);
    expect($job->fresh()->isPaused())->toBeFalse();
});

it('gives every queue independent worker capacity', function () {
    config(['queue.default' => 'database']);
    $this->artisan('catalogix:work --dry-run --discovery=2 --pages=2 --images=2 --exports=1 --default=1')
        ->expectsOutputToContain('[discovery-2]')
        ->expectsOutputToContain('[pages-2]')
        ->expectsOutputToContain('[images-2]')
        ->expectsOutputToContain('[exports-1]')
        ->expectsOutputToContain('[default-1]')
        ->assertSuccessful();
});
