<?php

namespace Brigada\Guardian\Models;

use Illuminate\Database\Eloquent\Model;

class OutgoingHttpLog extends Model
{
    public $timestamps = false;

    protected $table = 'guardian_outgoing_http_logs';

    protected $fillable = [
        'method', 'url', 'host', 'status_code',
        'duration_ms', 'failed', 'error_message', 'metadata', 'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'failed' => 'boolean',
        'duration_ms' => 'float',
        'created_at' => 'datetime',
    ];
}
