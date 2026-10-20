<?php

namespace App\Http\Controllers;

use App\Http\Requests\SaveRecipeRequest;
use App\Models\Site;
use App\Services\Crawling\UrlTools;
use App\Services\RecipeService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

/**
 * Visual recipe picker: the admin clicks one name, one price, one image and the Next button
 * on the real page; Catalogix turns the clicks into selectors, previews every match, and saves.
 */
class RecipeController extends Controller
{
    public function edit(Request $request, Site $site): Response
    {
        Gate::authorize('update', $site);

        $url = $request->string('url')->toString() ?: $this->suggestedUrl($site);

        return Inertia::render('recipes/edit', [
            'site' => [
                'id' => $site->id,
                'domain' => $site->domain,
                'needs_js' => $site->needs_js,
                'recipe_valid' => $site->recipe_valid,
                'selectors' => $site->selectors,
            ],
            'url' => $url,
            'frameUrl' => route('sites.recipe.frame', ['site' => $site, 'url' => $url]),
        ]);
    }

    /**
     * The script-free page shown inside the sandboxed picker frame.
     */
    public function frame(Request $request, Site $site, RecipeService $recipes): HttpResponse
    {
        Gate::authorize('update', $site);

        $url = $this->validatedUrl($request, $site);

        try {
            $html = $recipes->frame($site, $url, asset('recipe-picker.js'));
        } catch (RuntimeException $e) {
            $html = '<!doctype html><html><body><p>'.e($e->getMessage()).'</p></body></html>';
        }

        return response($html, 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Security-Policy' => "sandbox allow-scripts; default-src * data: blob: 'unsafe-inline'; script-src ".url('/').'/recipe-picker.js',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    public function preview(SaveRecipeRequest $request, Site $site, RecipeService $recipes): JsonResponse
    {
        try {
            return response()->json($recipes->preview($site, $request->validated('url'), $request->selectors()));
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }
    }

    public function update(SaveRecipeRequest $request, Site $site, RecipeService $recipes): RedirectResponse
    {
        $preview = $recipes->preview($site, $request->validated('url'), $request->selectors());

        if ($preview['products'] === []) {
            return back()->withErrors(['selectors' => 'These selectors match no products on the page. Pick the fields again.']);
        }

        $recipes->save($site, $request->selectors());

        Inertia::flash('toast', ['type' => 'success', 'message' => "Recipe saved for {$site->domain}. Re-run the job to use it."]);

        return to_route('sites.index');
    }

    private function validatedUrl(Request $request, Site $site): string
    {
        $url = $request->string('url')->toString();

        abort_unless($url !== '' && filter_var($url, FILTER_VALIDATE_URL) && UrlTools::isSameSite($url, $site->domain), 422, 'The page must belong to '.$site->domain.'.');

        return $url;
    }

    /**
     * The most recent route of this site that found nothing, or else its homepage.
     */
    private function suggestedUrl(Site $site): string
    {
        $failed = $site->discoveries()
            ->join('scrape_jobs', 'scrape_jobs.discovery_id', '=', 'discoveries.id')
            ->join('scrape_targets', 'scrape_targets.scrape_job_id', '=', 'scrape_jobs.id')
            ->where('scrape_targets.status', 'failed')
            ->latest('scrape_targets.updated_at')
            ->value('scrape_targets.url');

        return $failed ?? $site->baseUrl().'/';
    }
}
