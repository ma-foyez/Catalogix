<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Http\Resources\CategoryResource;
use App\Models\Site;
use App\Services\CatalogProductsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CategoryController extends Controller
{
    public function index(Request $request, CatalogProductsQuery $catalog): Response
    {
        $filters = $request->validate(['search' => ['nullable', 'string', 'max:200'], 'site' => ['nullable', 'integer']]);
        $ownProducts = $catalog->for($request->user())->reorder()->select('products.id');
        $siteIds = $catalog->for($request->user())->reorder()->select('site_id')->distinct();
        $categories = Category::with('site:id,domain')
            ->whereIn('site_id', $siteIds)
            ->withCount(['products' => fn ($products) => $products->whereIn('products.id', $ownProducts)])
            ->when($filters['site'] ?? null, fn ($query, $site) => $query->where('site_id', $site))
            ->when($filters['search'] ?? null, fn ($query, $search) => $query->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $search).'%'))
            ->orderBy('name')->paginate(24)->withQueryString();

        return Inertia::render('catalog/categories', [
            'categories' => CategoryResource::collection($categories),
            'sites' => Site::whereIn('id', $siteIds)->orderBy('domain')->get(['id', 'domain']),
            'filters' => array_replace(['search' => '', 'site' => null], $filters),
            'canManage' => (bool) $request->user()->is_admin,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);
        $data = $request->validate(['site_id' => ['required', 'integer', 'exists:sites,id'], 'name' => ['required', 'string', 'max:100']]);
        Category::resolve(Site::findOrFail($data['site_id']), $data['name']);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category created.']);

        return back();
    }

    public function update(Request $request, Category $category): RedirectResponse
    {
        abort_unless($request->user()->is_admin, 403);
        $request->merge(['slug' => Str::slug($request->string('name')->toString())]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['required', 'string', Rule::unique('categories', 'slug')->where('site_id', $category->site_id)->ignore($category->id)],
        ]);
        $category->update($data);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Category updated. Saved pictures remain available.']);

        return back();
    }
}
