<?php

use App\Domain\PriceAlert\Services\Redis\PriceAlertIndex;
use App\Models\PriceAlert;
use App\Models\User;
use Illuminate\Support\Facades\Redis;

beforeEach(function () {
    $this->user = User::factory()->create();
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
