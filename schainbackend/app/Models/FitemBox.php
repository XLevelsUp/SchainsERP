<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FitemBox extends Model
{
    protected $table = 'fitem_boxes';

    protected $primaryKey = 'box_id';

    public $timestamps = false;

    protected $fillable = [
        'box_name',
        'item_id',
        'is_active',
        'added_by',
        'updated_by',
        'added_at',
        'updated_at',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'added_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function item()
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }
}