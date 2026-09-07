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
    $this->deleteJson('/api/alerts/1')->assertUnauthorized();
});

it('deletes an active alert and removes it from the redis index', function (string $direction) {
    $alert = PriceAlert::factory()->active()->{$direction}()->create([
            'user_id' => $this->user->id,
            'target_price' => 3500,
        ]);

    app(PriceAlertIndex::class)->add(
        $alert->id,
        $alert->target_price,
        $alert->direction,
    );

    $key = $direction === 'above'
        ? PriceAlertIndex::ABOVE_KEY
        : PriceAlertIndex::BELOW_KEY;

    expect(Redis::zscore($key, $alert->id))->not->toBeFalse();

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/alerts/{$alert->id}")
        ->assertNoContent();

    $this->assertDatabaseMissing('price_alerts', ['id' => $alert->id]);

    expect(Redis::zscore($key, $alert->id))->toBeFalse()
        ->and(Redis::zrange($key, 0, -1))->toBe([]);
})->with([
    'above',
    'below',
]);

it('returns 404 for an unknown alert', function () {
    $this->actingAs($this->user, 'sanctum')
        ->deleteJson('/api/alerts/999999')
        ->assertNotFound();
});

it('does not allow deleting another user alert', function () {
    $otherUser = User::factory()->create();

    $alert = PriceAlert::factory()->active()->above()->create([
        'user_id' => $otherUser->id,
        'target_price' => 3500,
    ]);

    app(PriceAlertIndex::class)->add(
        $alert->id,
        $alert->target_price,
        $alert->direction,
    );

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/alerts/{$alert->id}")
        ->assertNotFound();

    $this->assertDatabaseHas('price_alerts', ['id' => $alert->id]);

    expect(Redis::zscore(PriceAlertIndex::ABOVE_KEY, $alert->id))->not->toBeFalse();
});

it('rejects deleting a triggered alert', function () {
    $alert = PriceAlert::factory()->triggered()->create([
        'user_id' => $this->user->id,
    ]);

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/alerts/{$alert->id}")
        ->assertStatus(400)
        ->assertJsonPath(
            'message',
            'Only active alerts that have not been triggered can be deleted.',
        );

    $this->assertDatabaseHas('price_alerts', [
        'id' => $alert->id,
        'status' => AlertStatus::TRIGGERED->value,
    ]);
});

it('a deleted alert can no longer be claimed by price crossing', function () {
    $alert = PriceAlert::factory()->active()->above()->create([
        'user_id' => $this->user->id,
        'target_price' => 3500,
    ]);

    app(PriceAlertIndex::class)->add(
        $alert->id,
        $alert->target_price,
        $alert->direction,
    );

    $this->actingAs($this->user, 'sanctum')
        ->deleteJson("/api/alerts/{$alert->id}")
        ->assertNoContent();

    $claimed = app(ProcessPriceCrossing::class)->execute(
        previousPrice: 3490,
        currentPrice: 3505,
    );

    expect($claimed)->toBe(0);
});
