<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiClient extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
        'revoked_at' => 'datetime',
    ];

    protected $hidden = [
        'client_secret_hash',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->revoked_at === null;
    }

    /**
     * Generate a new client_id + plain secret pair.
     * Returns [client_id, plain_secret, prefix].
     *
     * @return array{0:string,1:string,2:string}
     */
    public static function generateCredentials(): array
    {
        $clientId = 'acs_'.Str::random(32);
        $plainSecret = 'acsk_'.Str::random(40);
        $prefix = substr($plainSecret, 0, 11).'...'; // "acsk_abc..."

        return [$clientId, $plainSecret, $prefix];
    }
}
