<?php

use App\Models\ApiClient;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function createClient(array $overrides = []): array
{
    $user = User::factory()->create();
    [$clientId, $plainSecret, $prefix] = ApiClient::generateCredentials();

    $client = ApiClient::create(array_merge([
        'user_id' => $user->id,
        'name' => 'test client',
        'client_id' => $clientId,
        'client_secret_hash' => Hash::make($plainSecret),
        'client_secret_prefix' => $prefix,
        'scopes' => ['api:write'],
    ], $overrides));

    return [$client, $clientId, $plainSecret];
}

test('issueToken returns access token for valid credentials', function () {
    [, $clientId, $secret] = createClient();

    $response = $this->postJson('/api/auth/token', [
        'client_id' => $clientId,
        'client_secret' => $secret,
    ]);

    $response->assertOk()
        ->assertJsonStructure(['access_token', 'token_type', 'expires_in', 'scopes'])
        ->assertJsonPath('token_type', 'Bearer')
        ->assertJsonPath('expires_in', 3600);

    expect($response->json('access_token'))->toStartWith('at_');
});

test('issueToken rejects invalid client_id', function () {
    createClient();

    $this->postJson('/api/auth/token', [
        'client_id' => 'acs_nonexistent',
        'client_secret' => 'bogus',
    ])->assertStatus(401)
        ->assertJsonPath('error', 'invalid_client');
});

test('issueToken rejects wrong secret', function () {
    [, $clientId] = createClient();

    $this->postJson('/api/auth/token', [
        'client_id' => $clientId,
        'client_secret' => 'acsk_wrong_secret',
    ])->assertStatus(401)
        ->assertJsonPath('error', 'invalid_client');
});

test('issueToken rejects revoked client', function () {
    [$client, $clientId, $secret] = createClient();
    $client->update(['revoked_at' => now()]);

    $this->postJson('/api/auth/token', [
        'client_id' => $clientId,
        'client_secret' => $secret,
    ])->assertStatus(401)
        ->assertJsonPath('error', 'invalid_client');
});

test('issueToken enforces IP whitelist', function () {
    [, $clientId, $secret] = createClient(['allowed_ips' => '1.2.3.4']);

    $this->postJson('/api/auth/token', [
        'client_id' => $clientId,
        'client_secret' => $secret,
    ])->assertStatus(403)
        ->assertJsonPath('error', 'ip_not_allowed');
});

test('issueToken updates last_used_at timestamp', function () {
    [$client, $clientId, $secret] = createClient();

    expect($client->last_used_at)->toBeNull();

    $this->postJson('/api/auth/token', [
        'client_id' => $clientId,
        'client_secret' => $secret,
    ])->assertOk();

    expect($client->fresh()->last_used_at)->not->toBeNull();
});

test('GET /api/auth/me returns client info with valid token', function () {
    [$client, $clientId, $secret] = createClient();

    $token = $this->postJson('/api/auth/token', [
        'client_id' => $clientId,
        'client_secret' => $secret,
    ])->json('access_token');

    $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('client_id', $clientId)
        ->assertJsonPath('user_id', $client->user_id)
        ->assertJsonPath('scopes', ['api:write']);
});

test('GET /api/auth/me rejects missing token', function () {
    $this->getJson('/api/auth/me')
        ->assertStatus(401)
        ->assertJsonPath('error', 'missing_token');
});

test('GET /api/auth/me rejects invalid token', function () {
    $this->getJson('/api/auth/me', ['Authorization' => 'Bearer at_invalid'])
        ->assertStatus(401)
        ->assertJsonPath('error', 'invalid_token');
});

test('revokeToken clears token from cache', function () {
    [, $clientId, $secret] = createClient();

    $token = $this->postJson('/api/auth/token', [
        'client_id' => $clientId,
        'client_secret' => $secret,
    ])->json('access_token');

    // Token works before revoke
    $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])
        ->assertOk();

    // Revoke
    $this->postJson('/api/auth/revoke', [], ['Authorization' => "Bearer {$token}"])
        ->assertOk()
        ->assertJsonPath('status', 'revoked');

    // Token no longer works
    $this->getJson('/api/auth/me', ['Authorization' => "Bearer {$token}"])
        ->assertStatus(401);
});

test('issueToken validates required fields', function () {
    $this->postJson('/api/auth/token', [])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['client_id', 'client_secret']);
});

test('ApiClient generateCredentials returns well-formed pair', function () {
    [$clientId, $secret, $prefix] = ApiClient::generateCredentials();

    expect($clientId)->toStartWith('acs_');
    expect($secret)->toStartWith('acsk_');
    expect($prefix)->toContain('acsk_');
    expect(strlen($clientId))->toBeGreaterThan(30);
    expect(strlen($secret))->toBeGreaterThan(40);
});

test('ApiClient isActive returns false when revoked', function () {
    [$client] = createClient();

    expect($client->isActive())->toBeTrue();

    $client->update(['revoked_at' => now()]);

    expect($client->fresh()->isActive())->toBeFalse();
});
