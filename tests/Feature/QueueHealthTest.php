<?php

use App\Models\User;
use App\Services\DiscoveryService;
use App\Support\QueueHealth;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    config(['queue.default' => 'database']);
});

function waitingJob(string $queue, int $secondsAgo = 60, ?int $reservedAt = null): void
{
    DB::table('jobs')->insert([
        'queue' => $queue,
        'payload' => '{}',
        'attempts' => 0,
        'reserved_at' => $reservedAt,
        'available_at' => now()->subSeconds($secondsAgo)->getTimestamp(),
        'created_at' => now()->subSeconds($secondsAgo)->getTimestamp(),
    ]);
}

it('reports work that no worker is listening for', function () {
    waitingJob('discovery');
    waitingJob('exports');
    waitingJob('exports');

    expect(app(QueueHealth::class)->stalled())->toBe([
        'queues' => ['discovery', 'exports'],
        'waiting' => 3,
        'command' => QueueHealth::WORKER_COMMAND,
    ]);
});

it('stays quiet once a worker polls the queue', function () {
    waitingJob('discovery');

    QueueHealth::heartbeat('discovery,pages,images,exports,default');

    expect(app(QueueHealth::class)->stalled())->toBeNull();
});

it('does not flag a worker busy with a long job', function () {
    waitingJob('pages');
    waitingJob('discovery', reservedAt: now()->getTimestamp());

    expect(app(QueueHealth::class)->stalled())->toBeNull();
});

it('gives a freshly queued job a moment before calling it stalled', function () {
    waitingJob('discovery', secondsAgo: 2);

    expect(app(QueueHealth::class)->stalled())->toBeNull();
});

it('shares the stall with app pages and shows the discovery as waiting', function () {
    $user = User::factory()->create();
    $discovery = app(DiscoveryService::class)->start($user, 'chaldal.com');
    DB::table('jobs')->update(['available_at' => now()->subMinute()->getTimestamp()]);

    $this->actingAs($user)
        ->get(route('discoveries.show', $discovery))
        ->assertInertia(fn (Assert $page) => $page
            ->where('queueStalled.queues', ['discovery'])
            ->where('discovery.status', 'queued')
            ->where('discovery.stage', 'waiting')
            ->where('discovery.stage_label', 'Waiting for a worker'));
});
