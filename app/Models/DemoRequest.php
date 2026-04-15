<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DemoRequest extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'work_types' => 'array',
    ];
}
