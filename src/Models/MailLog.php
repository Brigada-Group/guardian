<?php

namespace Brigada\Guardian\Models;

use Illuminate\Database\Eloquent\Model;

class MailLog extends Model
{
    public $timestamps = false;

    protected $table = 'guardian_mail_logs';

    protected $fillable = [
        'mailable', 'subject', 'to', 'status',
        'error_message', 'metadata', 'created_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];
}
