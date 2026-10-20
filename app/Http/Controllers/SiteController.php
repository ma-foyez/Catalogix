<?php

namespace App\Http\Controllers;

use App\Http\Resources\SiteResource;
use App\Models\Site;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class SiteController extends Controller
{
    /**
     * Admin overview of every site: platform, winning strategy and recipe health.
     */
    public function index(): Response
    {
        Gate::authorize('viewAny', Site::class);

        return Inertia::render('sites/index', [
            'sites' => SiteResource::collection(Site::query()->withCount('products')->orderByDesc('updated_at')->paginate(30)),
        ]);
    }
}
