<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Role extends Model
{
    protected $table = 'roles';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'role',
        'added_at',
        'touch',
    ];

    protected $casts = [
        'added_at' => 'datetime',
        'touch' => 'float',
    ];
}