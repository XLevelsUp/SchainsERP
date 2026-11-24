<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** One manual balance change (see the create_opening_balance_updates_table migration). */
class OpeningBalanceUpdate extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'opening_balance_updates';

    protected $fillable = [
        'user_id', 'item_id', 'field', 'remarks',
        'previous_balance', 'value_update', 'closing_balance', 'admin_id',
    ];

    protected $casts = [
        'user_id' => 'integer',
        'item_id' => 'integer',
        'admin_id' => 'integer',
    ];
}
