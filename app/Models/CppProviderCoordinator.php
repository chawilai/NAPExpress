<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CppProviderCoordinator extends Model
{
    protected $table = 'cpp_provider_coordinators';

    protected $guarded = ['id'];

    public function provider(): BelongsTo
    {
        return $this->belongsTo(CppProvider::class, 'cpp_provider_id');
    }
}
