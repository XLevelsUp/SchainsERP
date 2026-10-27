<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SystemLog extends Model
{
    protected $table = 'system_logs';

    public $timestamps = false; // We use created_at with default current_timestamp

    protected $fillable = [
        'module',
        'action',
        'user_id',
        'reference_id',
        'reference_table',
        'description',
        'payload',
        'metadata',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected $casts = [
        'payload' => 'array',
        'metadata' => 'array',
        'created_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class, 'user_id', 'user_id');
    }
}
