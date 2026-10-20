<?php

namespace App\Services\Extraction;

use App\Enums\ExtractionStrategy;

final readonly class PageOutcome
{
    public const string NEXT = 'next';

    public const string DONE = 'done';

    public const string BLOCKED = 'blocked';

    public const string FAILED = 'failed';

    public function __construct(
        public string $status,
        public ?ExtractionStrategy $strategy = null,
        public ?UpsertResult $saved = null,
        public ?string $nextUrl = null,
        public ?string $message = null,
    ) {}

    public static function blocked(string $reason): self
    {
        return new self(self::BLOCKED, message: $reason);
    }

    public static function failed(string $message): self
    {
        return new self(self::FAILED, message: $message);
    }

    public static function done(?ExtractionStrategy $strategy = null, ?UpsertResult $saved = null): self
    {
        return new self(self::DONE, $strategy, $saved);
    }

    public function hasNext(): bool
    {
        return $this->status === self::NEXT && $this->nextUrl !== null;
    }
}
