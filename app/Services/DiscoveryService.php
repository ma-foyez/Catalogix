<?php

namespace App\Services;

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
    public function start(User $user, string $input): Discovery
    {
        $domain = UrlTools::domainFromInput($input) ?? throw new InvalidArgumentException('Enter a domain such as chaldal.com.');

        $discovery = Discovery::create([
            'user_id' => $user->id,
            'site_id' => Site::forHost($domain)->id,
            'status' => JobStatus::Queued,
        ]);

        DiscoverDomainJob::dispatch($discovery->id, $domain);

        return $discovery->load('site');
    }

    public function restart(Discovery $discovery): Discovery
    {
        $discovery->forceFill(['status' => JobStatus::Queued, 'error' => null, 'routes_found' => 0, 'finished_at' => null])->save();

        DiscoverDomainJob::dispatch($discovery->id, $discovery->site->domain);

        return $discovery;
    }
}
