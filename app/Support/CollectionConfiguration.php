<?php

namespace App\Support;

use App\Models\User;

final class CollectionConfiguration
{
    /** @return array{min_products: int, max_products: int|null, max_websites: int, max_routes: int, max_pages: int} */
    public static function for(User $user): array
    {
        return array_replace([
            'min_products' => 1,
            'max_products' => null,
            'max_websites' => DomainList::MAX,
            'max_routes' => (int) config('catalogix.auto_extract.max_routes', 25),
            'max_pages' => (int) config('catalogix.max_pages', 30),
        ], $user->collection_settings ?? []);
    }

    public static function maximum(User $user, ?int $requested = null): ?int
    {
        $settings = self::for($user);
        $maximum = $settings['max_products'];

        return $requested === null ? $maximum : ($maximum === null ? $requested : min($requested, $maximum));
    }
}
