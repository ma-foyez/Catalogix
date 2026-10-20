<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class ImageDownloaded implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  list<int>  $scrapeJobIds  jobs whose routes contain the product
     * @param  array<string, mixed>  $image
     */
    public function __construct(
        public array $scrapeJobIds,
        public int $productId,
        public array $image,
    ) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return array_map(fn (int $id) => new PrivateChannel('scrape-job.'.$id), $this->scrapeJobIds);
    }

    public function broadcastAs(): string
    {
        return 'image.downloaded';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return ['productId' => $this->productId, 'image' => $this->image];
    }
}
