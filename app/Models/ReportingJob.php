<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReportingJob extends Model
{
    use HasFactory;

    protected $fillable = [
        'organization_id',
        'user_id',
        'form_type',
        'method',
        'status',
        'counts',
        'ably_channel',
    ];

    protected $casts = [
        'counts' => 'array',
    ];

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function jobRows(): HasMany
    {
        return $this->hasMany(JobRow::class);
    }
}
