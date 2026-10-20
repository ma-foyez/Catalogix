<?php

namespace App\Jobs\Middleware;

use Closure;
use Illuminate\Support\Facades\Cache;

/**
 * Lets at most $slots jobs with the same key run at once (cache locks, so it works with the
 * database driver too). A job that finds every slot taken is released back onto the queue.
 */
final class ConcurrencySlots
{
    public function __construct(
        private readonly string $key,
        private readonly int $slots,
        private readonly int $releaseAfter = 3,
        private readonly int $lockSeconds = 300,
    ) {}

    public function handle(object $job, Closure $next): mixed
    {
        for ($slot = 0; $slot < max(1, $this->slots); $slot++) {
            $lock = Cache::lock("slots:{$this->key}:{$slot}", $this->lockSeconds);

            if ($lock->get()) {
                try {
                    return $next($job);
                } finally {
                    $lock->release();
                }
            }
        }

        if (method_exists($job, 'release')) {
            $job->release($this->releaseAfter);
        }

        return null;
    }
}
