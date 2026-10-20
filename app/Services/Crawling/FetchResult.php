<?php

namespace App\Services\Crawling;

final readonly class FetchResult
{
    public function __construct(
        public string $url,
        public string $finalUrl,
        public int $status,
        public string $body = '',
        public string $contentType = '',
        public ?string $blockedReason = null,
        public ?string $error = null,
    ) {}

    public static function blocked(string $url, string $reason): self
    {
        return new self($url, $url, 0, blockedReason: $reason);
    }

    public static function failed(string $url, string $error, int $status = 0): self
    {
        return new self($url, $url, $status, error: $error);
    }

    public function isBlocked(): bool
    {
        return $this->blockedReason !== null;
    }

    public function ok(): bool
    {
        return ! $this->isBlocked() && $this->error === null && $this->status >= 200 && $this->status < 300;
    }

    public function isHtml(): bool
    {
        return $this->contentType === '' || str_contains($this->contentType, 'html') || str_contains($this->contentType, 'xml');
    }

    /**
     * Decode a JSON body, or null when the body is not JSON.
     */
    public function json(): mixed
    {
        $decoded = json_decode($this->body, true);

        return json_last_error() === JSON_ERROR_NONE ? $decoded : null;
    }

    public function failureMessage(): string
    {
        return $this->blockedReason ?? $this->error ?? "HTTP {$this->status}";
    }
}
