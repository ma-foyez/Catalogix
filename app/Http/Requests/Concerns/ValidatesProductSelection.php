<?php

namespace App\Http\Requests\Concerns;

use App\Models\Product;
use App\Models\ScrapeJob;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

trait ValidatesProductSelection
{
    /** @return array<string, mixed> */
    protected function selectionRules(): array
    {
        return [
            'product_ids' => ['sometimes', 'array', 'min:1', 'max:10000'],
            'product_ids.*' => ['required', 'integer', 'distinct'],
            'excluded_product_ids' => ['sometimes', 'array', 'max:10000'],
            'excluded_product_ids.*' => ['required', 'integer', 'distinct'],
            'filters' => ['sometimes', 'array:route,category,search,image'],
            'filters.route' => ['nullable', 'integer'],
            'filters.category' => ['nullable', Rule::anyOf([['integer'], [Rule::in(['uncategorized'])]])],
            'filters.search' => ['nullable', 'string', 'max:200'],
            'filters.image' => ['nullable', Rule::in(['pending', 'downloaded', 'failed', 'none'])],
        ];
    }

    protected function validateSelection(Validator $validator, ?ScrapeJob $job): void
    {
        if ($validator->errors()->isNotEmpty() || ! $this->hasAny(['product_ids', 'excluded_product_ids', 'filters'])) {
            return;
        }

        if ($job === null || ! $this->user()?->can('view', $job)) {
            $validator->errors()->add('job_id', 'Choose one of your collections to export selected or filtered products.');

            return;
        }

        foreach (['product_ids', 'excluded_product_ids'] as $field) {
            $ids = $this->input($field, []);
            $count = Product::query()->whereIn('id', $ids)
                ->whereHas('scrapeTargets', fn ($query) => $query->where('scrape_job_id', $job->id))->count();

            if ($count !== count($ids)) {
                $validator->errors()->add($field, 'Every selected product must belong to this collection.');
            }
        }

        if ($this->filled('filters.route') && ! $job->targets()->whereKey($this->integer('filters.route'))->exists()) {
            $validator->errors()->add('filters.route', 'Choose a section from this collection.');
        }

        if ($this->filled('filters.category') && $this->input('filters.category') !== 'uncategorized' && ! $job->site()?->categories()->whereKey($this->integer('filters.category'))->exists()) {
            $validator->errors()->add('filters.category', 'Choose a category from this website.');
        }
    }
}
