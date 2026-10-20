<?php

namespace App\Enums;

enum TargetStatus: string
{
    case Queued = 'queued';
    case Crawling = 'crawling';
    case Done = 'done';
    case Blocked = 'blocked';
    case Failed = 'failed';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isFinished(): bool
    {
        return in_array($this, [self::Done, self::Blocked, self::Failed], true);
    }
}
