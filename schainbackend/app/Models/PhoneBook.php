<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A phone book contact (2026-11-24). Its own record: editing or deleting it
 * never changes a user. user_id links a contact to the user it was copied
 * from, if any (see the create_phone_book_table migration).
 */
class PhoneBook extends Model
{
    protected $table = 'phone_book';

    protected $fillable = ['name', 'phone_no', 'role_id', 'is_active', 'user_id'];

    protected $casts = [
        'role_id' => 'integer',
        'user_id' => 'integer',
        'is_active' => 'boolean',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class, 'user_id', 'user_id');
    }
}
