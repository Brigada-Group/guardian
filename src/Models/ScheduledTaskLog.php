<?php

namespace Brigada\Guardian\Models;

use Illuminate\Database\Eloquent\Model;

class ScheduledTaskLog extends Model
{
    public $timestamps = false;

    protected $table = 'guardian_scheduled_task_logs';

    protected $fillable = [
        'task', 'description', 'expression', 'status',
        'duration_ms', 'output', 'metadata', 'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'duration_ms' => 'float',
        'created_at' => 'datetime',
    ];
}
