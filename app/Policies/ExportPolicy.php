<?php

namespace App\Policies;

use App\Models\Export;
use App\Models\User;

class ExportPolicy
{
    public function view(User $user, Export $export): bool
    {
        return $export->user_id === $user->id || $user->is_admin;
    }

    public function delete(User $user, Export $export): bool
    {
        return $export->user_id === $user->id || $user->is_admin;
    }
}
