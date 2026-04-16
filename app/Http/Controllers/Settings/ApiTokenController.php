<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\ApiClient;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;

class ApiTokenController extends Controller
{
    public function index(Request $request): Response
    {
        $clients = ApiClient::where('user_id', $request->user()->id)
            ->orderByDesc('created_at')
            ->get()
            ->map(fn ($c) => [
                'id' => $c->id,
                'name' => $c->name,
                'client_id' => $c->client_id,
                'client_secret_prefix' => $c->client_secret_prefix,
                'allowed_ips' => $c->allowed_ips,
                'last_used_at' => $c->last_used_at,
                'revoked_at' => $c->revoked_at,
                'created_at' => $c->created_at,
                'is_active' => $c->isActive(),
            ]);

        return Inertia::render('settings/ApiTokens', [
            'clients' => $clients,
            'newCredentials' => session('newCredentials'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:200'],
            'allowed_ips' => ['nullable', 'string', 'max:500'],
        ]);

        [$clientId, $plainSecret, $prefix] = ApiClient::generateCredentials();

        ApiClient::create([
            'user_id' => $request->user()->id,
            'name' => $validated['name'],
            'client_id' => $clientId,
            'client_secret_hash' => Hash::make($plainSecret),
            'client_secret_prefix' => $prefix,
            'allowed_ips' => $validated['allowed_ips'] ?? null,
            'scopes' => ['api:write'],
        ]);

        return redirect()
            ->route('settings.api-tokens.index')
            ->with('newCredentials', [
                'name' => $validated['name'],
                'client_id' => $clientId,
                'client_secret' => $plainSecret,
            ]);
    }

    public function destroy(Request $request, ApiClient $apiClient): RedirectResponse
    {
        abort_if($apiClient->user_id !== $request->user()->id, 403);

        $apiClient->update(['revoked_at' => now()]);

        return redirect()
            ->route('settings.api-tokens.index')
            ->with('flash', 'ยกเลิก API client สำเร็จ');
    }
}
