<?php

use App\Models\Site;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    Queue::fake();
});

it('sends signed-in users from the dashboard to Discover', function () {
    $this->actingAs(User::factory()->create())->get('/dashboard')->assertRedirect('/discover');
});

it('renders every page of the app', function () {
    $user = User::factory()->create();
    $target = makeTarget('https://chaldal.com/popular', 'Popular');
    $target->scrapeJob->update(['user_id' => $user->id]);
    $target->scrapeJob->discovery->update(['user_id' => $user->id]);

    $this->actingAs($user);

    $this->get('/discover')->assertInertia(fn (Assert $page) => $page->component('discover/index')->has('recent', 1));
    $this->get(route('discoveries.show', $target->scrapeJob->discovery))->assertInertia(fn (Assert $page) => $page->component('discover/show')->has('discovery.routes', 1));
    $this->get('/jobs')->assertInertia(fn (Assert $page) => $page->component('jobs/index')->has('jobs.data', 1));
    $this->get(route('jobs.show', $target->scrapeJob))->assertInertia(fn (Assert $page) => $page
        ->component('jobs/show')
        ->has('job.routes', 1)
        ->has('products.data', 0)
        ->where('imageStats.failed', 0));
    $this->get('/exports')->assertInertia(fn (Assert $page) => $page->component('exports/index'));
});

it('keeps sites and the recipe picker for admins', function () {
    $site = Site::forHost('chaldal.com');

    $this->actingAs(User::factory()->create())->get('/sites')->assertForbidden();
    $this->get(route('sites.recipe.edit', $site))->assertForbidden();

    $this->actingAs(User::factory()->admin()->create());

    $this->get('/sites')->assertInertia(fn (Assert $page) => $page->component('sites/index')->has('sites.data', 1));
    $this->get(route('sites.recipe.edit', $site))->assertInertia(fn (Assert $page) => $page->component('recipes/edit')->where('url', 'https://chaldal.com/'));
});

it('serves a script-free sandboxed copy of the page to the recipe picker', function () {
    Http::fake([
        'chaldal.com/robots.txt' => Http::response('', 404),
        'chaldal.com/popular' => Http::response(str_replace('</body>', '<script>alert(1)</script><button onclick="steal()">x</button></body>', page('chaldal-popular.html'))),
    ]);

    $response = $this->actingAs(User::factory()->admin()->create())
        ->get(route('sites.recipe.frame', ['site' => Site::forHost('chaldal.com'), 'url' => 'https://chaldal.com/popular']))
        ->assertOk()
        ->assertHeader('Content-Security-Policy');

    expect($response->getContent())
        ->toContain('recipe-picker.js')
        ->toContain('<base href="https://chaldal.com/popular">')
        ->not->toContain('alert(1)')
        ->not->toContain('onclick');
});

it('previews and saves a picked recipe', function () {
    Http::fake([
        'chaldal.com/robots.txt' => Http::response('', 404),
        'chaldal.com/popular' => Http::response(page('chaldal-popular.html')),
    ]);

    $site = Site::forHost('chaldal.com');
    $this->actingAs(User::factory()->admin()->create());

    $payload = ['url' => 'https://chaldal.com/popular', 'selectors' => ['card' => 'div.productV2Catalog', 'name' => 'div.pvName', 'price' => 'div.productV2discountedPrice', 'image' => 'img']];

    $this->postJson(route('sites.recipe.preview', $site), $payload)
        ->assertOk()
        ->assertJsonPath('cards', 6)
        ->assertJsonPath('products.0.name', 'Tata Tea Premium')
        ->assertJsonPath('products.0.sale_price', 149);

    $this->put(route('sites.recipe.update', $site), $payload)->assertRedirect(route('sites.index'));

    expect($site->fresh())
        ->recipe_valid->toBeTrue()
        ->and($site->fresh()->selectors['card'])->toBe('div.productV2Catalog');
});

it('rejects a recipe that matches nothing', function () {
    Http::fake([
        'chaldal.com/robots.txt' => Http::response('', 404),
        'chaldal.com/popular' => Http::response(page('chaldal-popular.html')),
    ]);

    $this->actingAs(User::factory()->admin()->create())
        ->from('/sites')
        ->put(route('sites.recipe.update', Site::forHost('chaldal.com')), ['url' => 'https://chaldal.com/popular', 'selectors' => ['card' => '.nothing-here']])
        ->assertSessionHasErrors('selectors');
});
