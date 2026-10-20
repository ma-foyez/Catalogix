<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * An admin (sites and the visual recipe picker) and a regular user. Change both passwords outside local development.
     */
    public function run(): void
    {
        User::factory()->admin()->create([
            'name' => 'Catalogix Admin',
            'email' => 'admin@catalogix.test',
        ]);

        User::factory()->create([
            'name' => 'Demo User',
            'email' => 'user@catalogix.test',
        ]);
    }
}
