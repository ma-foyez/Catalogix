<?php

namespace App\Services;

use App\Enums\DiscoveryStage;
use App\Enums\JobStatus;
use App\Jobs\DiscoverDomainJob;
use App\Models\Discovery;
use App\Models\Site;
use App\Models\User;
use App\Services\Crawling\UrlTools;
use InvalidArgumentException;

final class DiscoveryService
{
    /**
     * Start route discovery for whatever the user typed: "chaldal.com", "www.chaldal.com" or a full URL.
     */
    public function start(User $user, string $input, bool $autoExtract = false): Discovery
    {
        $domain = UrlTools::domainFromInput($input) ?? throw new InvalidArgumentException('Enter a domain such as chaldal.com.');

        $discovery = Discovery::create([
            'user_id' => $user->id,
            'site_id' => Site::forHost($domain)->id,
            'auto_extract' => $autoExtract,
            'status' => JobStatus::Queued,
        ]);

        DiscoverDomainJob::dispatch($discovery->id, $domain);

        return $discovery->load('site');
    }

    /**
     * Start several sites at once. A site this user is already processing is left alone
     * instead of being queued twice.
     *
     * @param  list<string>  $domains
     * @return array{started: list<Discovery>, skipped: list<string>}
     */
    public function startMany(User $user, array $domains, bool $autoExtract = true): array
    {
        $started = [];
        $skipped = [];

        foreach ($domains as $domain) {
            $running = $user->discoveries()
                ->with('latestJob')
                ->whereHas('site', fn ($query) => $query->where('domain', $domain))
                ->latest()
                ->first();

            if ($running?->isActive()) {
                $skipped[] = $domain;

                continue;
            }

            $started[] = $this->start($user, $domain, $autoExtract);
        }

        return ['started' => $started, 'skipped' => $skipped];
    }

    public function restart(Discovery $discovery): Discovery
    {
        $discovery->forceFill([
            'status' => JobStatus::Queued,
            'stage' => DiscoveryStage::Waiting,
            'candidates' => 0,
            'probed' => 0,
            'to_probe' => 0,
            'error' => null,
            'routes_found' => 0,
            'started_at' => null,
            'finished_at' => null,
        ])->save();

        DiscoverDomainJob::dispatch($discovery->id, $discovery->site->domain);

        return $discovery;
    }
}
