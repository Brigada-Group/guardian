<?php

namespace Brigada\Guardian\Models;

use Illuminate\Database\Eloquent\Model;

class GuardianResult extends Model
{
    public $timestamps = false;

    protected $table = 'guardian_results';

    protected $fillable = [
        'check_class',
        'status',
        'message',
        'metadata',
        'notified_at',
        'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'notified_at' => 'datetime',
        'created_at' => 'datetime',
    ];
}
