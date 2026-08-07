<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CashTxnImage extends Model
{
    use HasFactory;

    protected $table = 'cash_txn_images';

    protected $primaryKey = 'image_id';

    protected $fillable = [
        'cash_txn_id',
        'image_path',
    ];

    protected $casts = [
        'image_id' => 'integer',
        'cash_txn_id' => 'integer',
    ];

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(CashTxnDetail::class, 'cash_txn_id', 'txn_id');
    }
}