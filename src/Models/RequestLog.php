<?php

namespace Brigada\Guardian\Models;

use Illuminate\Database\Eloquent\Model;

class RequestLog extends Model
{
    public $timestamps = false;

    protected $table = 'guardian_request_logs';

    protected $fillable = [
        'method', 'uri', 'route_name', 'status_code',
        'duration_ms', 'ip', 'user_id', 'metadata', 'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'duration_ms' => 'float',
        'created_at' => 'datetime',
    ];
}
