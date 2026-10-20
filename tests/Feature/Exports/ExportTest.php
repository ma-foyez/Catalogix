<?php

use App\Enums\ExportFormat;
use App\Enums\ExportLayout;
use App\Enums\ExportStatus;
use App\Enums\ImageStatus;
use App\Jobs\ExportProductsJob;
use App\Models\Category;
use App\Models\Export;
use App\Models\Product;
use App\Models\ProductImage;
use App\Models\ScrapeJob;
use App\Models\ScrapeTarget;
use App\Models\Site;
use App\Models\User;
use App\Services\ExportService;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpSpreadsheet\IOFactory;

/**
 * A finished job for one site with products in one category, thumbnails on the fake public disk.
 */
function jobWithProducts(User $user, string $domain, int $count, string $category = 'Popular'): ScrapeJob
{
    Queue::fake();
    $target = makeTarget("https://{$domain}/popular", $category);
    $job = $target->scrapeJob;
    $job->update(['user_id' => $user->id]);
    $job->discovery->update(['user_id' => $user->id]);

    $site = Site::forHost($domain);
    $categoryId = Category::resolve($site, $category)->id;

    foreach (range(1, $count) as $i) {
        $product = Product::create([
            'site_id' => $site->id,
            'category_id' => $categoryId,
            'hash' => sha1("{$domain}-{$i}"),
            'name' => "Miniket Rice {$i} kg",
            'price' => 80 * $i,
            'sale_price' => $i % 2 === 0 ? 75 * $i : null,
            'in_stock' => true,
            'product_url' => "https://{$domain}/p/rice-{$i}",
        ]);

        $thumb = "products/{$domain}/popular/rice-{$i}_thumb.jpg";
        Storage::disk('public')->put($thumb, pngImage(200, 200));
        Storage::disk('public')->put("products/{$domain}/popular/rice-{$i}.webp", pngImage(60, 60));

        ProductImage::create([
            'product_id' => $product->id,
            'source_url' => "https://{$domain}/img/rice-{$i}.jpg",
            'url_hash' => sha1("https://{$domain}/img/rice-{$i}.jpg"),
            'local_path' => "products/{$domain}/popular/rice-{$i}.webp",
            'thumb_path' => $thumb,
            'status' => ImageStatus::Downloaded,
        ]);

        $target->products()->attach($product->id);
    }

    return $job;
}

function runExport(User $user, array $data): Export
{
    $export = app(ExportService::class)->create($user, $data);
    runQueuedJobs(ExportProductsJob::class, 1);

    return $export->fresh();
}

beforeEach(function () {
    Storage::fake('public');
    Storage::fake('local');
    $this->user = User::factory()->create();
});

it('exports one site as CSV with a BOM, Bengali-safe', function () {
    $job = jobWithProducts($this->user, 'chaldal.com', 3);

    $export = runExport($this->user, ['scope' => 'site', 'job_id' => $job->id, 'format' => 'csv']);
    $csv = Storage::disk('local')->get($export->file_path);

    expect($export->status)->toBe(ExportStatus::Ready)
        ->and($export->row_count)->toBe(3)
        ->and($export->expires_at->isFuture())->toBeTrue()
        ->and($csv)->toStartWith("\xEF\xBB\xBF")
        ->and($csv)->toContain('Price (৳)')
        ->and($csv)->toContain('Miniket Rice 2 kg')
        ->and($csv)->toContain('chaldal.com');
});

it('exports all sites as one XLSX with a sheet per site', function () {
    jobWithProducts($this->user, 'chaldal.com', 3);
    jobWithProducts($this->user, 'deshibazar.com.bd', 2, 'Panjabi');

    $export = runExport($this->user, ['scope' => 'all', 'format' => 'xlsx', 'layout' => 'per_site']);
    $book = IOFactory::load(Storage::disk('local')->path($export->file_path));

    expect($book->getSheetNames())->toBe(['chaldal.com', 'deshibazar.com.bd'])
        ->and($book->getSheet(0)->getHighestRow())->toBe(4)
        ->and($book->getSheet(0)->getCell('A1')->getValue())->toBe('Name')
        ->and($book->getSheet(1)->getCell('A2')->getValue())->toBe('Miniket Rice 1 kg');
});

it('puts a thumbnail picture in every row of an Excel export with images', function () {
    $job = jobWithProducts($this->user, 'chaldal.com', 4);

    $export = runExport($this->user, ['scope' => 'site', 'job_id' => $job->id, 'format' => 'xlsx_images', 'layout' => 'single']);
    $sheet = IOFactory::load(Storage::disk('local')->path($export->file_path))->getSheet(0);

    $anchors = collect($sheet->getDrawingCollection())->map(fn ($drawing) => $drawing->getCoordinates())->all();

    expect($sheet->getCell('A1')->getValue())->toBe('Image')
        ->and($sheet->getCell('J1')->getValue())->toBe('Source')
        ->and($anchors)->toBe(['A2', 'A3', 'A4', 'A5'])
        ->and($sheet->getRowDimension(2)->getRowHeight())->toEqual(60.0);
});

it('splits Excel-with-images exports above the row limit into a ZIP of files', function () {
    config(['catalogix.exports.image_row_limit' => 2]);

    jobWithProducts($this->user, 'chaldal.com', 3);
    jobWithProducts($this->user, 'deshibazar.com.bd', 2, 'Panjabi');

    $export = runExport($this->user, ['scope' => 'all', 'format' => 'xlsx', 'embed_images' => true]);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($export->file_path));
    $names = collect(range(0, $zip->numFiles - 1))->map(fn ($i) => $zip->getNameIndex($i))->sort()->values()->all();
    $zip->close();

    expect($export->format)->toBe(ExportFormat::XlsxImages)
        ->and($export->file_path)->toEndWith('.zip')
        ->and($names)->toBe([
            'chaldal.com-popular-part-1.xlsx',
            'chaldal.com-popular-part-2.xlsx',
            'deshibazar.com.bd.xlsx',
        ]);
});

it('packs downloaded images into a ZIP with a folder per site and category', function () {
    $job = jobWithProducts($this->user, 'chaldal.com', 2);

    $export = runExport($this->user, ['scope' => 'site', 'job_id' => $job->id, 'format' => 'zip_images']);

    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($export->file_path));

    expect($zip->locateName('chaldal.com/popular/rice-1.webp'))->not->toBeFalse()
        ->and($zip->locateName('chaldal.com/popular/rice-2.webp'))->not->toBeFalse();

    $zip->close();
});

it('exports a single route', function () {
    $job = jobWithProducts($this->user, 'chaldal.com', 3);
    $route = $job->targets()->first();

    $export = runExport($this->user, ['scope' => 'route', 'route_id' => $route->id, 'format' => 'csv']);

    expect($export->row_count)->toBe(3)
        ->and($export->scrape_job_id)->toBe($job->id);
});

it('downloads through a signed link that expires', function () {
    $job = jobWithProducts($this->user, 'chaldal.com', 1);
    $export = runExport($this->user, ['scope' => 'site', 'job_id' => $job->id, 'format' => 'csv']);

    $this->get($export->downloadUrl())->assertOk()->assertDownload($export->fileName());
    $this->get(route('exports.download', $export))->assertForbidden();

    $export->update(['expires_at' => now()->subMinute()]);
    $this->get(URL::signedRoute('exports.download', $export))->assertStatus(410);
});

it('refuses to export another user\'s job', function () {
    $job = jobWithProducts(User::factory()->create(), 'chaldal.com', 1);

    $this->actingAs($this->user)
        ->post(route('exports.store'), ['scope' => 'site', 'job_id' => $job->id, 'format' => 'csv'])
        ->assertSessionHasErrors('job_id');
});

it('remembers the layout choice', function () {
    $job = jobWithProducts($this->user, 'chaldal.com', 1);

    $export = app(ExportService::class)->create($this->user, ['scope' => 'site', 'job_id' => $job->id, 'format' => 'xlsx', 'layout' => 'single']);

    expect($export->layout)->toBe(ExportLayout::Single)
        ->and(ScrapeTarget::count())->toBe(1);
});
