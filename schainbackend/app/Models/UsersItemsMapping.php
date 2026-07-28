<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UsersItemsMapping extends Model
{
    protected $table = 'users_items_mappings';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'item_id',
        'user_id',
        'added_at',
        'item_grams_total',
        'item_purity_total',
        'stone_cost_grand_total',
        'beads_cost_grand_total',
        'beads_stone_cost_grand_total',
        'last_txn_date',
        'is_primary',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'user_id' => 'integer',
        'added_at' => 'datetime',
        'stone_cost_grand_total' => 'float',
        'beads_cost_grand_total' => 'float',
        'beads_stone_cost_grand_total' => 'float',
        'last_txn_date' => 'datetime',
        'is_primary' => 'integer',
    ];
}