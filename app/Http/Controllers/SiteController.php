<?php

namespace App\Http\Controllers;

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

        $sites = Site::query()->withCount('products')->orderByDesc('updated_at')->paginate(30);

        return Inertia::render('sites/index', [
            'sites' => $sites->through(fn (Site $site) => [
                'id' => $site->id,
                'domain' => $site->domain,
                'platform' => $site->platform->label(),
                'strategy' => $site->strategy?->label(),
                'needs_js' => $site->needs_js,
                'has_recipe' => ! empty($site->selectors['card']),
                'recipe_valid' => $site->recipe_valid,
                'is_blocked' => $site->is_blocked,
                'blocked_reason' => $site->blocked_reason,
                'products_count' => $site->products_count,
                'last_success_at' => $site->last_success_at?->toIso8601String(),
            ]),
        ]);
    }
}
