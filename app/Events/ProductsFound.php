<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ProductsFound implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  list<array<string, mixed>>  $products
     */
    public function __construct(
        public int $scrapeJobId,
        public int $scrapeTargetId,
        public array $products,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('scrape-job.'.$this->scrapeJobId)];
    }

    public function broadcastAs(): string
    {
        return 'products.found';
    }
}
