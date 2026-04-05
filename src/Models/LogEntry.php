<?php

namespace Brigada\Guardian\Models;

use Illuminate\Database\Eloquent\Model;

class LogEntry extends Model
{
    public $timestamps = false;

    protected $table = 'guardian_log_entries';

    protected $fillable = [
        'level', 'message', 'channel',
        'context', 'created_at',
    ];

    protected $casts = [
        'context' => 'array',
        'created_at' => 'datetime',
    ];
}
