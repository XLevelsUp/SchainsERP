<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Laravel\Passport\HasApiTokens;

/**
 * An admin console account (table `admins`), authenticated through the
 * `admin` Passport guard. Not a user_details row: see App\Support\AdminAccess.
 */
class Admin extends Authenticatable
{
    use HasApiTokens;

    protected $table = 'admins';

    protected $fillable = [
        'name',
        'user_name',
        'password_hash',
        'special_password_hash',
        'is_active',
        'last_login_at',
        'password_changed_at',
        'special_password_changed_at',
    ];

    protected $hidden = [
        'password_hash',
        'special_password_hash',
        'remember_token',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_login_at' => 'datetime',
        'password_changed_at' => 'datetime',
        'special_password_changed_at' => 'datetime',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }
}
