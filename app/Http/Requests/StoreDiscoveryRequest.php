<?php

namespace App\Http\Requests;

use App\Services\Crawling\UrlTools;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreDiscoveryRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'domain' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (UrlTools::domainFromInput((string) $value) === null) {
                        $fail('Enter a domain such as chaldal.com.');
                    }
                },
            ],
        ];
    }
}
