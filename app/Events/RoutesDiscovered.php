<?php

namespace App\Events;

use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

class RoutesDiscovered implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets;

    /**
     * @param  array<string, mixed>  $discovery  discovery with its routes so far
     */
    public function __construct(public int $discoveryId, public array $discovery) {}

    /**
     * @return list<PrivateChannel>
     */
    public function broadcastOn(): array
    {
        return [new PrivateChannel('discovery.'.$this->discoveryId)];
    }

    public function broadcastAs(): string
    {
        return 'routes.discovered';
    }
}
