<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Reminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'description',
        'is_completed',
        'added_by',
        'assign_to',
        'remainder_at',
        'is_viewed',
    ];

    protected $casts = [
        'is_completed' => 'boolean',
        'is_viewed' => 'boolean',
        'remainder_at' => 'date',
    ];

    public function addedBy()
    {
        return $this->belongsTo(UserDetail::class, 'added_by', 'user_id');
    }

    public function assignTo()
    {
        return $this->belongsTo(UserDetail::class, 'assign_to', 'user_id');
    }
}
