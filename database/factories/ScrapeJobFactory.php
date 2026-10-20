<?php

namespace Database\Factories;

use App\Enums\JobStatus;
use App\Models\Discovery;
use App\Models\ScrapeJob;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScrapeJob>
 */
class ScrapeJobFactory extends Factory
{
    public function definition(): array
    {
        $discovery = Discovery::factory();

        return [
            'discovery_id' => $discovery,
            'user_id' => fn (array $attributes) => Discovery::whereKey($attributes['discovery_id'])->value('user_id'),
            'status' => JobStatus::Queued,
        ];
    }
}
