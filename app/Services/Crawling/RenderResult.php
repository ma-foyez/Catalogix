<?php

namespace App\Services\Crawling;

final readonly class RenderResult
{
    /**
     * @param  list<string>  $links
     * @param  list<array{url: string, body: mixed}>  $jsonResponses
     */
    public function __construct(
        public string $finalUrl,
        public string $html,
        public array $links = [],
        public array $jsonResponses = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, string $requestedUrl): self
    {
        $responses = [];

        foreach ((array) ($data['json_responses'] ?? []) as $response) {
            if (is_array($response) && isset($response['url'])) {
                $responses[] = ['url' => (string) $response['url'], 'body' => $response['body'] ?? null];
            }
        }

        return new self(
            finalUrl: (string) ($data['final_url'] ?? $requestedUrl),
            html: (string) ($data['html'] ?? ''),
            links: array_values(array_filter((array) ($data['links'] ?? []), 'is_string')),
            jsonResponses: $responses,
        );
    }
}
