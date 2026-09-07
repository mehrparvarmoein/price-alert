<?php

use App\Domain\PriceAlert\Enums\AlertStatus;
use App\Domain\PriceAlert\Services\ProcessPriceCrossing;
use App\Domain\PriceAlert\Services\Redis\PriceAlertIndex;
use App\Models\PriceAlert;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->user = User::factory()->create();
    Redis::del([
        'price_alerts:above',
        'price_alerts:below',
    ]);
});

it('requires authentication', function () {
    $this->postJson('/api/alerts', [
        'target_price' => 3500,
        'direction' => 'above',
    ])
        ->assertUnauthorized();
});

it('requires a valid target price', function () {
    $this->actingAs($this->user)
        ->postJson('/api/alerts', [
            'target_price' => 3500.12,
            'direction' => 'above',
        ])
        ->assertUnprocessable();
});

it('rejects an invalid direction', function () {
    $this->actingAs($this->user)
        ->postJson('/api/alerts', [
            'target_price' => 3500,
            'direction' => 'sideways',
        ])
        ->assertUnprocessable();
});

it('creates a price alert', function () {
    $response = $this->actingAs($this->user)
        ->postJson('/api/alerts', [
            'target_price' => 3500,
            'direction' => 'above',
        ]);

    $response->assertCreated()
        ->assertJsonPath(
            'data.target_price',
            3500
        )
        ->assertJsonPath(
            'data.direction',
            'above'
        )
        ->assertJsonPath(
            'data.status',
            'active'
        );

    $this->assertDatabaseHas('price_alerts', [
        'user_id' => $this->user->id,
        'target_price' => 3500,
        'direction' => 'above',
        'status' => 'active',
    ]);

    $alert = PriceAlert::firstWhere('user_id', $this->user->id);
    expect((int) Redis::zscore(PriceAlertIndex::ABOVE_KEY, $alert->id))->toBe(3500);

    $members = Redis::zrange(PriceAlertIndex::ABOVE_KEY, 0, -1);
    expect($members)->toBe([(string) $alert->id]);
});

it('indexes the created alert in redis', function ($direction) {

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/alerts', [
            'target_price' => 3500,
            'direction' => $direction,
        ])
        ->assertCreated();

    $alert = PriceAlert::firstWhere('user_id', $this->user->id);

    expect(
        (int) Redis::zscore(
            'price_alerts:' . $direction,
            $alert->id,
        )
    )->toBe(3500);
})->with([
    'above',
    'below',
]);

it('allows a user to create multiple alerts', function () {

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/alerts', [
            'target_price' => '3500',
            'direction' => 'above',
        ])
        ->assertCreated();

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/alerts', [
            'target_price' => '3600',
            'direction' => 'above',
        ])
        ->assertCreated();

    expect(
        PriceAlert::query()
            ->where('user_id', $this->user->id)
            ->count()
    )->toBe(2);
});

it('created alerts participate in price crossing detection', function () {

    $this->actingAs($this->user, 'sanctum')
        ->postJson('/api/alerts', [
            'target_price' => 3500,
            'direction' => 'above',
        ])
        ->assertCreated();


    $claimed = app(ProcessPriceCrossing::class)
        ->execute(
            previousPrice: 3499,
            currentPrice: 3501,
        );

    expect($claimed)->toBe(1);

    $alert = PriceAlert::firstWhere('user_id', $this->user->id);

    expect(PriceAlert::find($alert->id)->status)->toBe(AlertStatus::PROCESSING);
});
