<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AuditLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'association_id',
        'actor_user_id',
        'actor_role',
        'action',
        'entity_type',
        'entity_id',
        'change_summary',
        'before_data',
        'after_data',
        'ip_address',
        'user_agent',
        'request_id',
        'status',
        'error_message',
        'created_at',
    ];

    protected $casts = [
        'before_data' => 'array',
        'after_data' => 'array',
        'created_at' => 'datetime',
    ];
}

