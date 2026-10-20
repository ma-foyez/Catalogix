<?php

namespace App\Policies;

use App\Models\Product;
use App\Models\User;

class ProductPolicy
{
    public function update(User $user, Product $product): bool
    {
        // Source products are shared between collections. Only admins edit their canonical details.
        return $user->is_admin;
    }
}
