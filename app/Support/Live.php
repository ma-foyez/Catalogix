<?php

namespace App\Support;

use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Throwable;

/**
 * Broadcasts a live-progress event without letting a stopped Reverb server break the queue job.
 * Progress is also stored in MySQL, so a missed event only delays the screen until the next refresh.
 */
final class Live
{
    public static function send(ShouldBroadcast $event): void
    {
        try {
            event($event);
        } catch (Throwable $e) {
            logger()->warning('Live update not delivered: '.$e->getMessage(), ['event' => $event::class]);
        }
    }
}
