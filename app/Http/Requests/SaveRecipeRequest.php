<?php

namespace App\Http\Requests;

use App\Models\Site;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveRecipeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $site = $this->route('site');

        return $site instanceof Site && $this->user()?->can('update', $site);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'url' => ['required', 'url', 'max:2000'],
            'selectors' => ['required', 'array'],
            'selectors.card' => ['required', 'string', 'max:500'],
            'selectors.name' => ['nullable', 'string', 'max:500'],
            'selectors.price' => ['nullable', 'string', 'max:500'],
            'selectors.sale_price' => ['nullable', 'string', 'max:500'],
            'selectors.image' => ['nullable', 'string', 'max:500'],
            'selectors.link' => ['nullable', 'string', 'max:500'],
            'selectors.next' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string|null>
     */
    public function selectors(): array
    {
        $given = (array) $this->validated('selectors');
        $selectors = [];

        foreach (['card', 'name', 'price', 'sale_price', 'image', 'link', 'next'] as $field) {
            $value = $given[$field] ?? null;
            $selectors[$field] = is_string($value) && trim($value) !== '' ? trim($value) : null;
        }

        return $selectors;
    }
}
