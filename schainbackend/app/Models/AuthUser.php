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

    /**
     * True when the user's role has been switched off in the admin console.
     * Such users can't sign in, and a session they already hold ends on its
     * next request (EnsureAppSession). A role with no status saved counts as
     * active, as the frontend's isRoleActive() does; so does having no role.
     */
    public function hasInactiveRole(): bool
    {
        return $this->role?->is_active === false;
    }
}