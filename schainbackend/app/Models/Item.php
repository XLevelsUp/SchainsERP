<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Item extends Model
{
    protected $table = 'items';

    protected $primaryKey = 'item_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'item_name',
        'is_active',
        'default_touch',
        'item_touch',
        'added_at',
        'is_need_fitem_shown',
        'mtouch',
        'is_barcode',
        'is_no_barcode',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'default_touch' => 'float',
        'item_touch' => 'float',
        'added_at' => 'datetime',
        'is_need_fitem_shown' => 'boolean',
        'mtouch' => 'float',
        'is_barcode' => 'boolean',
        'is_no_barcode' => 'boolean',
    ];
}