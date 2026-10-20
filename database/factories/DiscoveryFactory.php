<?php

namespace Database\Factories;

use App\Enums\JobStatus;
use App\Models\Discovery;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Discovery>
 */
class DiscoveryFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'site_id' => Site::factory(),
            'status' => JobStatus::Queued,
        ];
    }
}
