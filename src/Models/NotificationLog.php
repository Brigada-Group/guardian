<?php

namespace Brigada\Guardian\Models;

use Illuminate\Database\Eloquent\Model;

class NotificationLog extends Model
{
    public $timestamps = false;

    protected $table = 'guardian_notification_logs';

    protected $fillable = [
        'notification_class', 'channel', 'notifiable_type', 'notifiable_id',
        'status', 'error_message', 'metadata', 'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
