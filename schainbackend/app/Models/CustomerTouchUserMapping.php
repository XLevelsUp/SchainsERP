<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CustomerTouchUserMapping extends Model
{
    protected $table = 'customer_touch_user_mappings';

    public $timestamps = false; // We manage added_at and updated_at explicitly

    protected $fillable = [
        'user_id',
        'customer_touch_id',
        'is_active',
        'added_at',
        'updated_at',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'customer_touch_id' => 'integer',
        'is_active' => 'boolean',
        'added_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(UserDetail::class, 'user_id', 'user_id');
    }

    public function customerTouch()
    {
        return $this->belongsTo(CustomerTouch::class, 'customer_touch_id', 'item_id');
    }
}
