<?php

use App\Models\ApiClient;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function () {
    $this->user = User::factory()->create();
});

test('unauthenticated user cannot view api tokens page', function () {
    $this->get('/settings/api-tokens')
        ->assertRedirect('/login');
});

test('authenticated user can view api tokens page', function () {
    $this->actingAs($this->user)
        ->get('/settings/api-tokens')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ApiTokens')
            ->has('clients')
            ->where('clients', [])
        );
});

test('authenticated user can create api client and see credentials once', function () {
    $response = $this->actingAs($this->user)
        ->post('/settings/api-tokens', [
            'name' => 'ACTSE Clinic production',
        ]);

    $response->assertRedirect('/settings/api-tokens');
    $response->assertSessionHas('newCredentials');

    $client = ApiClient::where('user_id', $this->user->id)->first();
    expect($client)->not->toBeNull();
    expect($client->name)->toBe('ACTSE Clinic production');
    expect($client->revoked_at)->toBeNull();
    expect($client->client_id)->toStartWith('acs_');
});

test('api client store validates required name', function () {
    $this->actingAs($this->user)
        ->post('/settings/api-tokens', [])
        ->assertSessionHasErrors(['name']);
});

test('api client store accepts IP whitelist', function () {
    $this->actingAs($this->user)
        ->post('/settings/api-tokens', [
            'name' => 'whitelisted',
            'allowed_ips' => '103.117.148.89, 43.229.150.41',
        ]);

    $client = ApiClient::where('user_id', $this->user->id)->first();
    expect($client->allowed_ips)->toBe('103.117.148.89, 43.229.150.41');
});

test('user can revoke their own api client', function () {
    [$clientId, $secret, $prefix] = ApiClient::generateCredentials();
    $client = ApiClient::create([
        'user_id' => $this->user->id,
        'name' => 'test',
        'client_id' => $clientId,
        'client_secret_hash' => bcrypt($secret),
        'client_secret_prefix' => $prefix,
        'scopes' => ['api:write'],
    ]);

    $this->actingAs($this->user)
        ->delete("/settings/api-tokens/{$client->id}")
        ->assertRedirect('/settings/api-tokens');

    expect($client->fresh()->revoked_at)->not->toBeNull();
});

test('user cannot revoke another users api client', function () {
    $otherUser = User::factory()->create();
    [$clientId, $secret, $prefix] = ApiClient::generateCredentials();
    $client = ApiClient::create([
        'user_id' => $otherUser->id,
        'name' => 'others',
        'client_id' => $clientId,
        'client_secret_hash' => bcrypt($secret),
        'client_secret_prefix' => $prefix,
        'scopes' => ['api:write'],
    ]);

    $this->actingAs($this->user)
        ->delete("/settings/api-tokens/{$client->id}")
        ->assertForbidden();

    expect($client->fresh()->revoked_at)->toBeNull();
});

test('index page returns only current user clients', function () {
    $otherUser = User::factory()->create();

    ApiClient::create([
        'user_id' => $this->user->id,
        'name' => 'mine',
        'client_id' => 'acs_mine',
        'client_secret_hash' => bcrypt('secret'),
        'client_secret_prefix' => 'acsk_...',
        'scopes' => ['api:write'],
    ]);

    ApiClient::create([
        'user_id' => $otherUser->id,
        'name' => 'theirs',
        'client_id' => 'acs_theirs',
        'client_secret_hash' => bcrypt('secret'),
        'client_secret_prefix' => 'acsk_...',
        'scopes' => ['api:write'],
    ]);

    $this->actingAs($this->user)
        ->get('/settings/api-tokens')
        ->assertInertia(fn (Assert $page) => $page
            ->component('settings/ApiTokens')
            ->has('clients', 1)
            ->where('clients.0.name', 'mine')
        );
});
