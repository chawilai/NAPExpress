<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CppScrapeQueue extends Model
{
    protected $table = 'cpp_scrape_queue';

    protected $guarded = ['id'];

    protected $casts = [
        'claimed_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    public $timestamps = true;
}
