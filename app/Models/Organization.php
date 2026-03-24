<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Organization extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'hcode',
        'verified',
        'subscription',
        'api_enabled',
    ];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function reportingJobs(): HasMany
    {
        return $this->hasMany(ReportingJob::class);
    }

    public function apiKeys(): HasMany
    {
        return $this->hasMany(ApiKey::class);
    }
}
