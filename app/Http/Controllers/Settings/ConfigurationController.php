<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Support\CollectionConfiguration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ConfigurationController extends Controller
{
    public function edit(Request $request): Response
    {
        return Inertia::render('settings/configuration', [
            'configuration' => CollectionConfiguration::for($request->user()),
            'limits' => [
                'max_websites' => \App\Support\DomainList::MAX,
                'max_routes' => (int) config('catalogix.auto_extract.max_routes', 25),
                'max_pages' => (int) config('catalogix.max_pages', 30),
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = $request->validate([
            'min_products' => ['required', 'integer', 'min:1', 'max:100000'],
            'max_products' => ['nullable', 'integer', 'gte:min_products', 'max:100000'],
            'max_websites' => ['required', 'integer', 'min:1', 'max:'.\App\Support\DomainList::MAX],
            'max_routes' => ['required', 'integer', 'min:1', 'max:'.config('catalogix.auto_extract.max_routes', 25)],
            'max_pages' => ['required', 'integer', 'min:1', 'max:'.config('catalogix.max_pages', 30)],
        ]);
        $settings = array_map(fn ($value) => $value === null ? null : (int) $value, $settings);
        $request->user()->forceFill(['collection_settings' => $settings])->save();
        Inertia::flash('toast', ['type' => 'success', 'message' => 'Collection configuration saved. It applies to new collections.']);

        return back();
    }
}
