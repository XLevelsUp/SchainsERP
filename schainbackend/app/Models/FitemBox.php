<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FitemBox extends Model
{
    protected $table = 'fitem_boxes';

    protected $primaryKey = 'box_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'box_name',
        'item_id',
        'is_active',
        'added_by',
        'added_at',
        'updated_by',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'is_active' => 'boolean',
        'added_by' => 'integer',
        'updated_by' => 'integer',
        'added_at' => 'datetime',
    ];
}