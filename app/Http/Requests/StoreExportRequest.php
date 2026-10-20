<?php

namespace App\Http\Requests;

use App\Enums\ExportFormat;
use App\Enums\ExportLayout;
use App\Enums\ExportScope;
use App\Http\Requests\Concerns\ValidatesProductSelection;
use App\Models\ScrapeJob;
use App\Models\ScrapeTarget;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreExportRequest extends FormRequest
{
    use ValidatesProductSelection;

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...$this->selectionRules(),
            'scope' => ['required', Rule::enum(ExportScope::class)],
            'job_id' => ['nullable', 'required_if:scope,site', 'integer', 'exists:scrape_jobs,id'],
            'route_id' => ['nullable', 'required_if:scope,route', 'integer', 'exists:scrape_targets,id'],
            'format' => ['required', Rule::enum(ExportFormat::class)],
            'layout' => ['nullable', Rule::enum(ExportLayout::class)],
            'embed_images' => ['nullable', 'boolean'],
            'category_id' => ['nullable', 'integer', 'exists:categories,id'],
        ];
    }

    /**
     * The user must own the job or route they export.
     *
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $user = $this->user();

                if ($this->filled('job_id') && ($job = ScrapeJob::find($this->integer('job_id'))) && ! $user?->can('view', $job)) {
                    $validator->errors()->add('job_id', 'You cannot export this job.');
                }

                if ($this->filled('route_id') && ($target = ScrapeTarget::with('scrapeJob')->find($this->integer('route_id'))) && ! $user?->can('view', $target->scrapeJob)) {
                    $validator->errors()->add('route_id', 'You cannot export this route.');
                }

                $job = $this->input('scope') === 'route'
                    ? ScrapeTarget::find($this->integer('route_id'))?->scrapeJob
                    : ($this->input('scope') === 'site' ? ScrapeJob::find($this->integer('job_id')) : null);
                $this->validateSelection($validator, $job);
            },
        ];
    }
}
