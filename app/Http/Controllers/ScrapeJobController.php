<?php

namespace App\Http\Controllers;

use App\Enums\ImageStatus;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ScrapeJobResource;
use App\Models\ProductImage;
use App\Models\ScrapeJob;
use App\Services\ExtractionService;
use App\Services\JobProductsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use stdClass;

class ScrapeJobController extends Controller
{
    /**
     * History: past extraction jobs.
     */
    public function index(Request $request): Response
    {
        $jobs = $request->user()->scrapeJobs()->with('discovery.site')->latest()->paginate(20);

        return Inertia::render('jobs/index', [
            'jobs' => ScrapeJobResource::collection($jobs),
        ]);
    }

    /**
     * The live product listing of one job.
     */
    public function show(Request $request, ScrapeJob $job, JobProductsQuery $products): Response
    {
        Gate::authorize('view', $job);

        $filters = $request->only(['route', 'category', 'search', 'image']);

        return Inertia::render('jobs/show', [
            'job' => (new ScrapeJobResource($job->load(['discovery.site', 'targets.discoveredRoute'])))->resolve(),
            'products' => ProductResource::collection($products->for($job, $filters)->paginate(50)->withQueryString()),
            'filters' => [
                'route' => $filters['route'] ?? null,
                'category' => $filters['category'] ?? null,
                'search' => $filters['search'] ?? '',
                'image' => $filters['image'] ?? null,
            ],
            'categories' => $this->categories($job),
            'imageStats' => $this->imageStats($job),
            'isAdmin' => (bool) $request->user()->is_admin,
        ]);
    }

    public function rerun(ScrapeJob $job, ExtractionService $extraction): RedirectResponse
    {
        Gate::authorize('update', $job);

        $new = $extraction->rerun($job);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Extraction started again.']);

        return to_route('jobs.show', $new);
    }

    public function retryImages(ScrapeJob $job, ExtractionService $extraction): RedirectResponse
    {
        Gate::authorize('update', $job);

        $count = $extraction->retryImages($job);

        Inertia::flash('toast', ['type' => 'success', 'message' => $count > 0 ? "Retrying {$count} images." : 'No failed images to retry.']);

        return back();
    }

    public function destroy(ScrapeJob $job): RedirectResponse
    {
        Gate::authorize('delete', $job);

        $job->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Job deleted. Products stay in the catalogue.']);

        return to_route('jobs.index');
    }

    /**
     * @return list<array{id: int, name: string, count: int}>
     */
    private function categories(ScrapeJob $job): array
    {
        return array_values(DB::table('categories')
            ->select('categories.id', 'categories.name')
            ->selectRaw('count(distinct products.id) as product_count')
            ->join('products', 'products.category_id', '=', 'categories.id')
            ->join('product_scrape_target', 'product_scrape_target.product_id', '=', 'products.id')
            ->join('scrape_targets', 'scrape_targets.id', '=', 'product_scrape_target.scrape_target_id')
            ->where('scrape_targets.scrape_job_id', $job->id)
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('categories.name')
            ->get()
            ->map(fn (stdClass $row) => ['id' => (int) $row->id, 'name' => (string) $row->name, 'count' => (int) $row->product_count])
            ->all());
    }

    /**
     * @return array<string, int>
     */
    private function imageStats(ScrapeJob $job): array
    {
        $counts = ProductImage::query()
            ->whereHas('product.scrapeTargets', fn ($query) => $query->where('scrape_targets.scrape_job_id', $job->id))
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $stats = [];

        foreach (ImageStatus::cases() as $status) {
            $stats[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return $stats;
    }
}
