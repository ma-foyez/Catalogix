<?php

namespace Database\Factories;

use App\Enums\Platform;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Site>
 */
class SiteFactory extends Factory
{
    public function definition(): array
    {
        $domain = fake()->unique()->domainWord().'.com.bd';

        return [
            'domain' => $domain,
            'name' => $domain,
            'platform' => Platform::Unknown,
        ];
    }
}
