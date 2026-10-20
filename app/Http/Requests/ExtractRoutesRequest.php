<?php

namespace App\Http\Requests;

use App\Models\Discovery;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ExtractRoutesRequest extends FormRequest
{
    public function authorize(): bool
    {
        $discovery = $this->route('discovery');

        return $discovery instanceof Discovery && $this->user()?->can('update', $discovery);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var Discovery $discovery */
        $discovery = $this->route('discovery');
        $settings = \App\Support\CollectionConfiguration::for($this->user());

        return [
            'max_products' => ['nullable', 'integer', 'min:'.$discovery->min_products, 'max:'.($settings['max_products'] ?? 100000)],
            'route_ids' => ['required', 'array', 'min:1', 'max:500'],
            'route_ids.*' => ['integer', Rule::exists('discovered_routes', 'id')->where('discovery_id', $discovery->id)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'route_ids.required' => 'Tick at least one route to extract.',
            'route_ids.min' => 'Tick at least one route to extract.',
        ];
    }

    /**
     * @return list<int>
     */
    public function routeIds(): array
    {
        return array_values(array_map('intval', (array) $this->validated('route_ids')));
    }
}
