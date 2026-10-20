<?php

use App\Enums\ExportStatus;
use App\Enums\RouteSource;
use App\Jobs\DiscoverDomainJob;
use App\Jobs\ExportProductsJob;
use App\Jobs\ProcessPageJob;
use App\Models\DiscoveredRoute;
use App\Models\Discovery;
use App\Models\Export;
use App\Models\Product;
use App\Models\Site;
use App\Models\User;
use App\Services\Crawling\UrlTools;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Queue::fake();
});

it('issues a token for valid credentials', function () {
    $user = User::factory()->create(['email' => 'anik@intelifa.com']);

    $this->postJson('/api/tokens', ['email' => 'anik@intelifa.com', 'password' => 'password', 'device_name' => 'android'])
        ->assertCreated()
        ->assertJsonStructure(['token', 'token_type']);

    $this->postJson('/api/tokens', ['email' => 'anik@intelifa.com', 'password' => 'wrong', 'device_name' => 'android'])
        ->assertUnprocessable();

    expect($user->tokens()->count())->toBe(1);
});

it('requires a token', function () {
    $this->postJson('/api/discoveries', ['domain' => 'chaldal.com'])->assertUnauthorized();
});

it('runs the domain → routes → extract flow', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $id = $this->postJson('/api/discoveries', ['domain' => 'chaldal.com'])
        ->assertStatus(202)
        ->assertJsonPath('data.status', 'queued')
        ->json('data.id');

    Queue::assertPushed(DiscoverDomainJob::class);

    $discovery = Discovery::findOrFail($id);
    $route = DiscoveredRoute::create([
        'discovery_id' => $discovery->id, 'url' => 'https://chaldal.com/popular', 'url_hash' => UrlTools::hash('https://chaldal.com/popular'),
        'path' => '/popular', 'category_name' => 'Popular', 'source' => RouteSource::Menu, 'score' => 88, 'est_products' => 48,
        'sample_images' => ['https://i.chaldn.com/a.jpg'], 'is_selected' => true,
    ]);

    $this->getJson("/api/discoveries/{$id}")
        ->assertOk()
        ->assertJsonPath('data.routes.0.path', '/popular')
        ->assertJsonPath('data.routes.0.score_label', 'high')
        ->assertJsonPath('data.routes.0.est_products', 48);

    $jobId = $this->postJson("/api/discoveries/{$id}/extract", ['route_ids' => [$route->id]])
        ->assertStatus(202)
        ->assertJsonPath('data.total_routes', 1)
        ->assertJsonPath('data.routes.0.category', 'Popular')
        ->json('data.id');

    Queue::assertPushed(ProcessPageJob::class, fn ($job) => $job->url === 'https://chaldal.com/popular');

    $this->getJson("/api/jobs/{$jobId}")
        ->assertOk()
        ->assertJsonPath('data.status', 'queued')
        ->assertJsonPath('images.failed', 0);
});

it('lists job products with local image URLs and filters', function () {
    Sanctum::actingAs($user = User::factory()->create());

    $target = makeTarget('https://chaldal.com/popular', 'Popular');
    $target->scrapeJob->update(['user_id' => $user->id]);

    $site = Site::forHost('chaldal.com');

    foreach (['Tata Tea Premium', 'Miniket Rice (Premium)'] as $name) {
        $product = Product::create(['site_id' => $site->id, 'hash' => sha1($name), 'name' => $name, 'price' => 100]);
        $product->image()->create(['source_url' => 'https://i.chaldn.com/'.sha1($name), 'url_hash' => sha1($name), 'local_path' => 'products/x.webp', 'thumb_path' => 'products/x_thumb.jpg', 'status' => 'downloaded']);
        $target->products()->attach($product->id);
    }

    $response = $this->getJson("/api/jobs/{$target->scrape_job_id}/products?search=rice")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.name', 'Miniket Rice (Premium)');

    expect($response->json('data.0.image.thumb_url'))->toEndWith('/storage/products/x_thumb.jpg');
});

it('creates exports and returns a signed download URL when ready', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $target = makeTarget('https://chaldal.com/popular');
    $target->scrapeJob->update(['user_id' => $user->id]);

    $id = $this->postJson('/api/exports', ['job_id' => $target->scrape_job_id, 'scope' => 'site', 'format' => 'xlsx', 'embed_images' => true, 'layout' => 'per_site'])
        ->assertStatus(202)
        ->assertJsonPath('data.format', 'xlsx_images')
        ->json('data.id');

    Queue::assertPushed(ExportProductsJob::class);

    Export::find($id)->update(['status' => ExportStatus::Ready, 'file_path' => 'exports/1/file.xlsx', 'bytes' => 1200, 'expires_at' => now()->addDay()]);

    $url = $this->getJson("/api/exports/{$id}")->assertOk()->assertJsonPath('data.bytes', 1200)->json('data.download_url');

    expect($url)->toContain('/api/exports/'.$id.'/download')->toContain('signature=');
});

it('hides other users\' discoveries, jobs and exports', function () {
    $owner = User::factory()->create();
    $target = makeTarget('https://chaldal.com/popular');
    $target->scrapeJob->update(['user_id' => $owner->id]);
    $target->scrapeJob->discovery->update(['user_id' => $owner->id]);

    Sanctum::actingAs(User::factory()->create());

    $this->getJson("/api/discoveries/{$target->scrapeJob->discovery_id}")->assertForbidden();
    $this->getJson("/api/jobs/{$target->scrape_job_id}")->assertForbidden();
    $this->getJson("/api/jobs/{$target->scrape_job_id}/products")->assertForbidden();
    $this->postJson("/api/jobs/{$target->scrape_job_id}/images/retry")->assertForbidden();
});

it('validates extract requests against the discovery', function () {
    Sanctum::actingAs($user = User::factory()->create());
    $discovery = Discovery::factory()->for($user)->create();
    $other = Discovery::factory()->create();
    $foreign = DiscoveredRoute::create([
        'discovery_id' => $other->id, 'url' => 'https://x.com.bd/a', 'url_hash' => sha1('a'), 'path' => '/a', 'source' => RouteSource::Menu,
    ]);

    $this->postJson("/api/discoveries/{$discovery->id}/extract", ['route_ids' => [$foreign->id]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('route_ids.0');
});
