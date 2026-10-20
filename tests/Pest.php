<?php

use App\Enums\RouteSource;
use App\Enums\TargetStatus;
use App\Jobs\DownloadImageJob;
use App\Jobs\ProcessPageJob;
use App\Models\DiscoveredRoute;
use App\Models\Discovery;
use App\Models\ScrapeJob;
use App\Models\ScrapeTarget;
use App\Models\Site;
use App\Services\Crawling\UrlTools;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| Feature tests run against the MySQL test database (catalogix_test). HTTP and
| processes are faked in every test: no live site and no Python helper is ever hit.
|
*/

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature');

pest()->extend(TestCase::class)->in('Unit');

/**
 * Contents of a saved HTML/JSON fixture from tests/Fixtures (Pest's fixture() gives the path).
 */
function page(string $name): string
{
    return (string) file_get_contents(fixture($name));
}

/**
 * A real PNG of the given size, for image download tests.
 */
function pngImage(int $width = 400, int $height = 400): string
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 117, 51, 203));

    for ($i = 0; $i < 400; $i++) {
        imagesetpixel($image, random_int(0, $width - 1), random_int(0, $height - 1), imagecolorallocate($image, random_int(0, 255), random_int(0, 255), random_int(0, 255)));
    }

    ob_start();
    imagepng($image);

    return (string) ob_get_clean();
}

/**
 * A discovery, a ticked route and a scrape job with one queued target for the URL, and its first page job.
 */
function makeTarget(string $url, ?string $category = null): ScrapeTarget
{
    $site = Site::forHost((string) parse_url($url, PHP_URL_HOST));
    $discovery = Discovery::factory()->for($site)->create();

    $route = DiscoveredRoute::create([
        'discovery_id' => $discovery->id,
        'url' => $url,
        'url_hash' => UrlTools::hash($url),
        'path' => UrlTools::pathWithQuery($url),
        'category_name' => $category,
        'source' => RouteSource::Menu,
        'score' => 80,
        'is_selected' => true,
    ]);

    $job = ScrapeJob::factory()->create(['discovery_id' => $discovery->id, 'user_id' => $discovery->user_id, 'total_routes' => 1]);

    $target = $job->targets()->create(['discovered_route_id' => $route->id, 'url' => $url, 'status' => TargetStatus::Queued]);

    ProcessPageJob::dispatch($target->id, $url);

    return $target;
}

/**
 * Run faked ProcessPageJobs one after another (each page queues the next), like a worker would.
 */
function runPageJobs(int $limit = 30): void
{
    runQueuedJobs(ProcessPageJob::class, $limit);
}

function runImageJobs(int $limit = 100): void
{
    runQueuedJobs(DownloadImageJob::class, $limit);
}

/**
 * @param  class-string  $class
 */
function runQueuedJobs(string $class, int $limit): void
{
    $ran = [];

    while (count($ran) < $limit) {
        $pending = Queue::pushed($class)->reject(fn (object $job) => isset($ran[spl_object_id($job)]));

        if ($pending->isEmpty()) {
            return;
        }

        foreach ($pending as $job) {
            $ran[spl_object_id($job)] = true;
            app()->call([$job, 'handle']);
        }
    }
}
