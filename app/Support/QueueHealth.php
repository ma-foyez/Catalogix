<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Notices when work is waiting but no queue worker is listening, so the UI can say so
 * instead of showing "Queued" forever.
 */
final class QueueHealth
{
    /** The queues Catalogix dispatches to; a worker must listen on all of them. */
    public const array QUEUES = ['discovery', 'pages', 'images', 'exports'];

    /** Command shown to the user when nothing is listening. */
    public const string WORKER_COMMAND = 'php artisan queue:work --queue=exports,pages,discovery,images,default --timeout=960';

    private const string SEEN_KEY = 'catalogix:queue-seen:';

    /** A worker counts as alive this long after it last polled a queue. */
    private const int SEEN_FOR = 90;

    /** Jobs waiting longer than this with no worker are reported as stalled. */
    private const int STALL_AFTER = 10;

    /** @var array<string, int> last heartbeat written per queue, per process */
    private static array $written = [];

    /**
     * Called each time a worker polls: remembers which queues have a listener.
     */
    public static function heartbeat(?string $queues): void
    {
        foreach (explode(',', (string) $queues) as $queue) {
            if (! in_array($queue, self::QUEUES, true) || (self::$written[$queue] ?? 0) > time() - 15) {
                continue;
            }

            self::$written[$queue] = time();
            Cache::put(self::SEEN_KEY.$queue, time(), self::SEEN_FOR);
        }
    }

    /**
     * Queues holding work that no worker has picked up.
     *
     * @return array{queues: list<string>, waiting: int, command: string}|null null while everything is moving
     */
    public function stalled(): ?array
    {
        if (config('queue.default') !== 'database') {
            return null;
        }

        $table = (string) config('queue.connections.database.table', 'jobs');

        // A worker busy with a long job (a discovery can take minutes) stops polling; that is not a stall.
        if (DB::table($table)->whereIn('queue', self::QUEUES)->whereNotNull('reserved_at')->exists()) {
            return null;
        }

        /** @var array<string, int> $waiting */
        $waiting = DB::table($table)
            ->whereIn('queue', self::QUEUES)
            ->whereNull('reserved_at')
            ->where('available_at', '<=', now()->subSeconds(self::STALL_AFTER)->getTimestamp())
            ->groupBy('queue')
            ->selectRaw('queue, count(*) as total')
            ->pluck('total', 'queue')
            ->map(fn ($total) => (int) $total)
            ->all();

        $stalled = array_values(array_filter(
            array_keys($waiting),
            fn (string $queue) => ! Cache::has(self::SEEN_KEY.$queue),
        ));

        if ($stalled === []) {
            return null;
        }

        return [
            'queues' => $stalled,
            'waiting' => array_sum(array_intersect_key($waiting, array_flip($stalled))),
            'command' => self::WORKER_COMMAND,
        ];
    }
}
