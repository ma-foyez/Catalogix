<?php

namespace App\Services\Crawling;

use App\Exceptions\RenderFailedException;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use Throwable;

/**
 * Runs scraper/render.py (Python + Playwright) for JavaScript-heavy pages and returns the rendered
 * HTML, every link, and the JSON responses the page fetched for itself.
 */
final class PageRenderer
{
    public function __construct(private readonly RobotsPolicy $robots) {}

    /**
     * @throws RenderFailedException
     */
    public function render(string $url, int $scroll = 0): RenderResult
    {
        if (! $this->robots->allows($url)) {
            throw new RenderFailedException('robots.txt does not allow crawling this page.');
        }

        $directory = storage_path('app/tmp');
        File::ensureDirectoryExists($directory);

        $out = $directory.DIRECTORY_SEPARATOR.'render-'.Str::uuid().'.json';

        try {
            $result = Process::timeout((int) config('catalogix.render.process_timeout', 90))->run([
                (string) config('catalogix.python_bin', 'python'),
                (string) config('catalogix.render.script'),
                '--url', $url,
                '--scroll', (string) $scroll,
                '--timeout', (string) config('catalogix.render.timeout', 45),
                '--user-agent', (string) config('catalogix.user_agent'),
                '--out', $out,
            ]);
        } catch (Throwable $e) {
            throw new RenderFailedException('Could not start the Python helper: '.$e->getMessage());
        }

        try {
            if ($result->failed()) {
                throw RenderFailedException::fromProcess($result->errorOutput(), $result->exitCode());
            }

            if (! is_file($out)) {
                throw new RenderFailedException('The Python helper did not write its output file.');
            }

            $data = json_decode((string) file_get_contents($out), true);

            if (! is_array($data)) {
                throw new RenderFailedException('The Python helper wrote invalid JSON.');
            }

            return RenderResult::fromArray($data, $url);
        } finally {
            if (is_file($out)) {
                @unlink($out);
            }
        }
    }
}
