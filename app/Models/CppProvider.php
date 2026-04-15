<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CppProvider extends Model
{
    protected $table = 'cpp_providers';

    protected $guarded = ['id'];

    protected $casts = [
        'latitude' => 'decimal:7',
        'longitude' => 'decimal:7',
        'cpp_last_updated' => 'date',
        'scraped_at' => 'datetime',
    ];

    public function networkTypes(): HasMany
    {
        return $this->hasMany(CppProviderNetworkType::class);
    }

    public function coordinators(): HasMany
    {
        return $this->hasMany(CppProviderCoordinator::class);
    }
}
