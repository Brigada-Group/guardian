<?php

namespace Brigada\Guardian\Models;

use Illuminate\Database\Eloquent\Model;

class QueryLog extends Model
{
    public $timestamps = false;

    protected $table = 'guardian_query_logs';

    protected $fillable = [
        'sql', 'duration_ms', 'connection', 'file', 'line',
        'is_slow', 'is_n_plus_one', 'metadata', 'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'is_slow' => 'boolean',
        'is_n_plus_one' => 'boolean',
        'duration_ms' => 'float',
        'created_at' => 'datetime',
    ];
}
