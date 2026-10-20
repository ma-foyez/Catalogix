<?php

namespace App\Http\Controllers\Api;

use App\Enums\ImageStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Http\Resources\ScrapeJobResource;
use App\Models\ProductImage;
use App\Models\ScrapeJob;
use App\Services\ExtractionService;
use App\Services\JobProductsQuery;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class ScrapeJobController extends Controller
{
    /**
     * GET /api/jobs/{id}: job status, per-route progress, product and image counts.
     */
    public function show(ScrapeJob $job): ScrapeJobResource
    {
        Gate::authorize('view', $job);

        $counts = ProductImage::query()
            ->whereHas('product.scrapeTargets', fn ($query) => $query->where('scrape_targets.scrape_job_id', $job->id))
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        $images = [];

        foreach (ImageStatus::cases() as $status) {
            $images[$status->value] = (int) ($counts[$status->value] ?? 0);
        }

        return (new ScrapeJobResource($job->load(['discovery.site', 'targets.discoveredRoute'])))->additional(['images' => $images]);
    }

    /**
     * GET /api/jobs/{id}/products?route=&category=&search=&page=: products with local image and thumbnail URLs.
     */
    public function products(Request $request, ScrapeJob $job, JobProductsQuery $products): AnonymousResourceCollection
    {
        Gate::authorize('view', $job);

        $request->validate([
            'route' => ['nullable', 'integer'],
            'category' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:200'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        return ProductResource::collection(
            $products->for($job, $request->only(['route', 'category', 'search']))
                ->paginate($request->integer('per_page', 50))
                ->withQueryString(),
        );
    }

    /**
     * POST /api/jobs/{id}/images/retry: queue failed image downloads again.
     */
    public function retryImages(ScrapeJob $job, ExtractionService $extraction): JsonResponse
    {
        Gate::authorize('update', $job);

        return response()->json(['queued' => $extraction->retryImages($job)], 202);
    }
}
