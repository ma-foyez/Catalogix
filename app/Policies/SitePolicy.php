<?php

namespace App\Policies;

use App\Models\Site;
use App\Models\User;

/**
 * Sites and their recipes are shared by everyone, so only admins change them (visual recipe picker).
 */
class SitePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->is_admin;
    }

    public function update(User $user, Site $site): bool
    {
        return $user->is_admin;
    }
}
