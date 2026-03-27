<?php

namespace Brigada\Guardian\Models;

use Illuminate\Database\Eloquent\Model;

class CommandLog extends Model
{
    public $timestamps = false;

    protected $table = 'guardian_command_logs';

    protected $fillable = [
        'command', 'exit_code', 'duration_ms',
        'arguments', 'metadata', 'created_at',
    ];

    protected $casts = [
        'arguments' => 'array',
        'metadata' => 'array',
        'duration_ms' => 'float',
        'created_at' => 'datetime',
    ];
}
