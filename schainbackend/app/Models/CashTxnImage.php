<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTxnImage extends Model
{
    protected $table = 'cash_txn_images';

    protected $primaryKey = 'image_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'txn_id',
        'image_url',
        'added_at',
    ];

    protected $casts = [
        'image_id' => 'integer',
        'txn_id' => 'integer',
        'added_at' => 'datetime',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(
            CashTxnDetail::class,
            'txn_id',
            'txn_id'
        );
    }
}