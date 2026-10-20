<?php

namespace App\Http\Requests;

use App\Http\Requests\Concerns\ValidatesProductSelection;
use App\Models\ScrapeJob;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class QuickExportRequest extends FormRequest
{
    use ValidatesProductSelection;

    public function authorize(): bool
    {
        $job = $this->route('job');

        return $job instanceof ScrapeJob && $this->user()?->can('view', $job);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return $this->selectionRules();
    }

    /** @return list<\Closure(Validator): void> */
    public function after(): array
    {
        return [fn (Validator $validator) => $this->validateSelection($validator, $this->route('job'))];
    }
}
