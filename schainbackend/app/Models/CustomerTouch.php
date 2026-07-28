<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerTouch extends Model
{
    protected $table = 'customer_touch';

    protected $primaryKey = 'item_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'item_name',
        'is_active',
        'added_at',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'item_name' => 'string',
        'is_active' => 'boolean',
        'added_at' => 'datetime',
    ];
}