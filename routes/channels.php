<?php

use App\Models\Discovery;
use App\Models\ScrapeJob;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function (User $user, int $id) {
    return $user->id === $id;
});

Broadcast::channel('discovery.{discovery}', function (User $user, Discovery $discovery) {
    return $user->can('view', $discovery);
});

Broadcast::channel('scrape-job.{job}', function (User $user, ScrapeJob $job) {
    return $user->can('view', $job);
});
