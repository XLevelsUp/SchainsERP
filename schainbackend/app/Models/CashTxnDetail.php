<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CashTxnDetail extends Model
{
    use HasFactory;

    protected $table = 'cash_txn_details';

    protected $primaryKey = 'txn_id';

    protected $fillable = [
        'type',
        'sender_id',
        'recipient_id',
        'category_id',
        'amount',
        'balance_after_txn',
        'sender_opening_cash',
        'sender_opening_rtgs',
        'recipient_opening_cash',
        'recipient_opening_rtgs',
        'sender_closing_cash',
        'sender_closing_rtgs',
        'recipient_closing_cash',
        'recipient_closing_rtgs',
        'payment_method',
        'bank_account_id',
        'remarks',
        'added_by',
    ];

    protected $casts = [
        'txn_id' => 'integer',
        'sender_id' => 'integer',
        'recipient_id' => 'integer',
        'category_id' => 'integer',
        'amount' => 'decimal:2',
        'balance_after_txn' => 'decimal:2',
        'sender_opening_cash' => 'decimal:2',
        'sender_opening_rtgs' => 'decimal:2',
        'recipient_opening_cash' => 'decimal:2',
        'recipient_opening_rtgs' => 'decimal:2',
        'sender_closing_cash' => 'decimal:2',
        'sender_closing_rtgs' => 'decimal:2',
        'recipient_closing_cash' => 'decimal:2',
        'recipient_closing_rtgs' => 'decimal:2',
        'bank_account_id' => 'integer',
        'added_by' => 'integer',
    ];

    public function givenByUser(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class, 'sender_id', 'user_id');
    }

    public function givenToUser(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class, 'recipient_id', 'user_id');
    }

    public function bank(): BelongsTo
    {
        return $this->belongsTo(BankDetail::class, 'bank_account_id', 'bank_id');
    }

    public function images(): HasMany
    {
        return $this->hasMany(CashTxnImage::class, 'cash_txn_id', 'txn_id');
    }
}