<?php

use App\Models\User;

beforeEach(function () {
    $this->user = User::factory()->create();
});

it('requires an email and password', function () {
    $this->postJson('/api/login', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('rejects an invalid email format', function () {
    $this->postJson('/api/login', [
        'email' => 'not-an-email',
        'password' => 'password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

it('rejects an unknown email', function () {
    $this->postJson('/api/login', [
        'email' => 'unknown@example.com',
        'password' => 'password',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid credentials.');
});

it('rejects a wrong password', function () {
    $this->postJson('/api/login', [
        'email' => $this->user->email,
        'password' => 'wrong-password',
    ])
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Invalid credentials.');
});

it('logs in with valid credentials and returns a usable token', function () {
    $response = $this->postJson('/api/login', [
        'email' => $this->user->email,
        'password' => 'password',
    ]);

    $response
        ->assertOk()
        ->assertJsonPath('data.user.email', $this->user->email)
        ->assertJsonStructure(['data' => ['token', 'user' => ['name', 'email']]]);

    $token = $response->json('data.token');
    expect($token)->toBeString()->not->toBeEmpty();

    $this->withHeader('Authorization', "Bearer {$token}")
        ->postJson('/api/alerts', [
            'target_price' => 3500,
            'direction' => 'above',
        ])
        ->assertCreated();
});