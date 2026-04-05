<?php

namespace Brigada\Guardian\Models;

use Illuminate\Database\Eloquent\Model;

class JobLog extends Model
{
    public $timestamps = false;

    protected $table = 'guardian_job_logs';

    protected $fillable = [
        'job_class', 'queue', 'connection', 'status',
        'duration_ms', 'attempt', 'error_message',
        'metadata', 'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'duration_ms' => 'float',
        'created_at' => 'datetime',
    ];
}
