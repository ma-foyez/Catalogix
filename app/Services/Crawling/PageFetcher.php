<?php

namespace App\Services\Crawling;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Fetches pages with the Laravel HTTP client: robots.txt first, a fixed user agent,
 * a 20 s timeout with 2 retries on connection errors, and block detection on the response.
 */
final class PageFetcher
{
    public function __construct(
        private readonly RobotsPolicy $robots,
        private readonly BlockDetector $blocks,
    ) {}

    public function get(string $url, string $accept = 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8'): FetchResult
    {
        if (! $this->robots->allows($url)) {
            return FetchResult::blocked($url, 'robots.txt does not allow crawling this page.');
        }

        try {
            $response = $this->client()->accept($accept)->get($url);
        } catch (ConnectionException $e) {
            return FetchResult::failed($url, 'Could not connect: '.$e->getMessage());
        } catch (Throwable $e) {
            return FetchResult::failed($url, $e->getMessage());
        }

        return $this->toResult($url, $response);
    }

    /**
     * Fetch a JSON endpoint (platform APIs). Returns null on any failure.
     */
    public function json(string $url): mixed
    {
        $result = $this->get($url, 'application/json');

        return $result->ok() ? $result->json() : null;
    }

    private function client(): PendingRequest
    {
        return Http::withUserAgent((string) config('catalogix.user_agent'))
            ->withHeaders(['Accept-Language' => 'en-US,en;q=0.9,bn;q=0.8'])
            ->timeout((int) config('catalogix.http.timeout', 20))
            ->retry(
                (int) config('catalogix.http.retries', 2) + 1,
                500,
                fn (Throwable $e) => $e instanceof ConnectionException,
                throw: false,
            );
    }

    private function toResult(string $url, Response $response): FetchResult
    {
        $finalUrl = (string) ($response->effectiveUri() ?? $url);
        $body = $response->body();
        $status = $response->status();

        $blocked = $this->blocks->reason($url, $finalUrl, $status, $body);

        return new FetchResult(
            url: $url,
            finalUrl: $finalUrl,
            status: $status,
            body: $body,
            contentType: strtolower($response->header('Content-Type')),
            blockedReason: $blocked,
            error: $blocked === null && ! $response->successful() ? "HTTP {$status}" : null,
        );
    }
}
