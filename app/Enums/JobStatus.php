<?php

namespace App\Enums;

/**
 * Lifecycle shared by discoveries and scrape jobs.
 */
enum JobStatus: string
{
    case Queued = 'queued';
    case Running = 'running';
    case Done = 'done';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Done, self::Failed], true);
    }
}
