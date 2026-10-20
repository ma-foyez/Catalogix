<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Job status, totals and per-route progress, sent after every page.
 */
class ScrapeProgress implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $job
     */
    public function __construct(public int $scrapeJobId, public array $job) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('scrape-job.'.$this->scrapeJobId)];
    }

    public function broadcastAs(): string
    {
        return 'scrape.progress';
    }
}
