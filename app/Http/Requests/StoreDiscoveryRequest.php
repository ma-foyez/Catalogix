<?php

namespace App\Http\Requests;

use App\Support\DomainList;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * One website or many: "domains" takes pasted text (commas, spaces or new lines) or a list;
 * "domain" is the single-site form the REST API documents.
 */
class StoreDiscoveryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'domains' => ['required_without:domain', 'nullable'],
            'domains.*' => ['string', 'max:2000'],
            'domain' => ['required_without:domains', 'nullable', 'string', 'max:2000'],
            'auto_extract' => ['nullable', 'boolean'],
            'max_products' => ['nullable', 'integer', 'min:1', 'max:100000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'domains.required_without' => 'Enter at least one website, such as chaldal.com.',
            'domain.required_without' => 'Enter a domain such as chaldal.com.',
        ];
    }

    /**
     * @return list<Closure(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($validator->errors()->isNotEmpty()) {
                    return;
                }

                $field = $this->has('domains') ? 'domains' : 'domain';
                $parsed = $this->parsed();

                if ($parsed['invalid'] !== []) {
                    $validator->errors()->add($field, count($parsed['invalid']) === 1
                        ? "“{$parsed['invalid'][0]}” doesn't look like a website address. Try something like chaldal.com."
                        : 'These don\'t look like website addresses: '.implode(', ', $parsed['invalid']).'.');
                } elseif ($parsed['domains'] === []) {
                    $validator->errors()->add($field, 'Enter at least one website, such as chaldal.com.');
                } elseif (count($parsed['domains']) > DomainList::MAX) {
                    $validator->errors()->add($field, 'Add up to '.DomainList::MAX.' websites at a time.');
                }
            },
        ];
    }

    /**
     * @return list<string>
     */
    public function domains(): array
    {
        return $this->parsed()['domains'];
    }

    /**
     * @return array{domains: list<string>, invalid: list<string>}
     */
    private function parsed(): array
    {
        $input = $this->input('domains', $this->input('domain', ''));

        return DomainList::parse(is_array($input) ? array_values(array_map('strval', $input)) : (string) $input);
    }
}
