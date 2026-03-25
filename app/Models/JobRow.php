<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class JobRow extends Model
{
    use HasFactory;

    protected $fillable = [
        'reporting_job_id',
        'row_number',
        'pid_masked',
        'row_data',
        'status',
        'nap_response_code',
        'error_message',
    ];

    protected $casts = [
        'row_data' => 'array',
    ];

    public function reportingJob(): BelongsTo
    {
        return $this->belongsTo(ReportingJob::class);
    }
}
