<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Http\Resources\ScrapeJobResource;
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
            'job' => (new ScrapeJobResource($job->load(['discovery.site', 'targets' => fn ($query) => $query->with('discoveredRoute')->withCount('products')])))->resolve(),
            'products' => ProductResource::collection($products->for($job, $filters)->paginate(50)->withQueryString()),
            'filters' => [
                'route' => $filters['route'] ?? null,
                'category' => $filters['category'] ?? null,
                'search' => $filters['search'] ?? '',
                'image' => $filters['image'] ?? null,
            ],
            'categories' => $this->categories($job, $products, $filters),
            'imageStats' => $job->imageStats(),
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
     * @param  array{route?: int|string|null, category?: int|string|null, search?: string|null, image?: string|null}  $filters
     * @return list<array{id: int|null, name: string, count: int}>
     */
    private function categories(ScrapeJob $job, JobProductsQuery $products, array $filters): array
    {
        unset($filters['category']);
        $productIds = $products->for($job, $filters)->reorder()->select('products.id');

        return array_values(DB::table('products')
            ->select('categories.id', 'categories.name')
            ->selectRaw('count(distinct products.id) as product_count')
            ->leftJoin('categories', 'products.category_id', '=', 'categories.id')
            ->whereIn('products.id', $productIds)
            ->groupBy('categories.id', 'categories.name')
            ->orderBy('categories.name')
            ->get()
            ->map(fn (stdClass $row) => ['id' => $row->id !== null ? (int) $row->id : null, 'name' => $row->name ?? 'Uncategorized', 'count' => (int) $row->product_count])
            ->all());
    }
}
