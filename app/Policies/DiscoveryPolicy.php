<?php

namespace App\Policies;

use App\Models\Discovery;
use App\Models\User;

class DiscoveryPolicy
{
    public function view(User $user, Discovery $discovery): bool
    {
        return $user->is_admin || $discovery->user_id === $user->id;
    }

    public function update(User $user, Discovery $discovery): bool
    {
        return $discovery->user_id === $user->id || $user->is_admin;
    }

    public function delete(User $user, Discovery $discovery): bool
    {
        return $discovery->user_id === $user->id || $user->is_admin;
    }
}
