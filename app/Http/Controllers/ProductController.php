<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProductResource;
use App\Models\Category;
use App\Models\Product;
use App\Models\Site;
use App\Services\CatalogProductsQuery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    public function index(Request $request, CatalogProductsQuery $catalog): Response
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'site' => ['nullable', 'integer'],
            'category' => ['nullable', Rule::anyOf([['integer'], [Rule::in(['uncategorized'])]])],
            'image' => ['nullable', Rule::in(['downloaded', 'pending', 'failed'])],
        ]);
        $base = $catalog->for($request->user());
        $siteIds = (clone $base)->reorder()->select('site_id')->distinct();
        $categoryIds = (clone $base)->reorder()->select('category_id')->distinct();

        return Inertia::render('catalog/products', [
            'products' => ProductResource::collection($catalog->for($request->user(), $filters)->paginate(30)->withQueryString()),
            'filters' => array_replace(['search' => '', 'site' => null, 'category' => null, 'image' => null], $filters),
            'sites' => Site::whereIn('id', $siteIds)->orderBy('domain')->get(['id', 'domain']),
            'categories' => Category::with('site:id,domain')->whereIn('site_id', $siteIds)
                ->when(! $request->user()->is_admin, fn ($query) => $query->whereIn('id', $categoryIds))
                ->orderBy('name')->get(['id', 'site_id', 'name'])
                ->map(fn ($category) => ['id' => $category->id, 'site_id' => $category->site_id, 'name' => $category->name, 'domain' => $category->site->domain]),
            'stats' => [
                'products' => (clone $base)->count(),
                'websites' => (clone $base)->reorder()->distinct()->count('site_id'),
                'categories' => (clone $base)->reorder()->distinct()->count('category_id'),
                'pictures' => (clone $base)->whereHas('image', fn ($images) => $images->where('status', 'downloaded'))->count(),
            ],
            'canManage' => (bool) $request->user()->is_admin,
        ]);
    }

    public function update(Request $request, Product $product): RedirectResponse
    {
        Gate::authorize('update', $product);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:500'],
            'brand' => ['nullable', 'string', 'max:190'],
            'sku' => ['nullable', 'string', 'max:190'],
            'description' => ['nullable', 'string', 'max:5000'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where('site_id', $product->site_id)],
            'price' => ['nullable', 'required_with:sale_price', 'numeric', 'min:0', 'max:9999999999'],
            'sale_price' => ['nullable', 'numeric', 'min:0', 'lte:price'],
            'in_stock' => ['nullable', 'boolean'],
        ]);
        $product->update($data);
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Product details updated.']);

        return back();
    }
}
