<?php

namespace Brigada\Guardian\Models;

use Illuminate\Database\Eloquent\Model;

class CacheLog extends Model
{
    public $timestamps = false;

    protected $table = 'guardian_cache_logs';

    protected $fillable = [
        'store', 'hits', 'misses', 'writes', 'forgets',
        'hit_rate', 'period_start', 'created_at',
    ];

    protected $casts = [
        'hits' => 'integer',
        'misses' => 'integer',
        'writes' => 'integer',
        'forgets' => 'integer',
        'hit_rate' => 'float',
        'period_start' => 'datetime',
        'created_at' => 'datetime',
    ];
}
