<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Passport\HasApiTokens;

class AuthUser extends Authenticatable
{
    use HasApiTokens, Notifiable;

    protected $table = 'user_details';

    protected $primaryKey = 'user_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'user_name',
        'password_hash',
    ];

    // report_password and otp are secrets too; login and /me returned them
    // to the browser until 2026-11-21 (UserDetail already hid them).
    protected $hidden = [
        'password_hash',
        'report_password',
        'otp',
    ];

    public function getAuthPassword()
    {
        return $this->password_hash;
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }
}