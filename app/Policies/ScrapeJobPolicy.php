<?php

namespace App\Policies;

use App\Models\ScrapeJob;
use App\Models\User;

class ScrapeJobPolicy
{
    public function view(User $user, ScrapeJob $job): bool
    {
        return $user->is_admin || $job->user_id === $user->id;
    }

    public function update(User $user, ScrapeJob $job): bool
    {
        return $job->user_id === $user->id || $user->is_admin;
    }

    public function delete(User $user, ScrapeJob $job): bool
    {
        return $job->user_id === $user->id || $user->is_admin;
    }
}
