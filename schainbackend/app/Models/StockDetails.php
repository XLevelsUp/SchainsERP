<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

class StockDetails extends Model
{
    protected $table = 'stock_details';
    protected $primaryKey = 'stock_id';

    protected $fillable = [
        'item_id',
        'given_by',
        'given_to',
        'type',
        'entry_type',
        'stock_type',
        'grams',
        'no_of_pcs',
        'touch',
        'purity',
        'remarks',
        'waste_total',
        'waste_value',
        'mtouch',
        'gms_mtouch',
        'gms_mthouch_wastage',
        'waste_id',
        'bill_id',
        'balance',
        'stock_in_id',
        'is_freezed',
        'is_completed',
        'is_receiver_completed',
        'is_hided',
        'added_by',
        'to_item_id',
        'given_by_item_grams_op',
        'given_to_item_grams_op',
        'given_by_item_purity_op',
        'given_to_item_purity_op',
        'given_by_item_grams_cb',
        'given_to_item_grams_cb',
        'given_by_item_purity_cb',
        'given_to_item_purity_cb',
        'item_remarks',
        'stock_in_ob',
        'stock_in_cb',
        'stock_in_ob_purity',
        'stock_in_cb_purity',
        'retailer_id',
        'retailer_op_grams',
        'retailer_op_purity',
        'to_retailer_id',
        'to_retailer_op_grams',
        'to_retailer_op_purity',
        'reply_history_json',
        'obcb_details',
        'added_at',
    ];

    protected $casts = [
        'item_id' => 'integer',
        'given_by' => 'integer',
        'given_to' => 'integer',
        'grams' => 'decimal:4',
        'touch' => 'decimal:4',
        'purity' => 'decimal:4',
        'waste_total' => 'decimal:4',
        'waste_value' => 'decimal:4',
        'mtouch' => 'decimal:4',
        'gms_mtouch' => 'decimal:4',
        'gms_mthouch_wastage' => 'decimal:4',
        'waste_id' => 'integer',
        'bill_id' => 'integer',
        'balance' => 'decimal:4',
        'stock_in_id' => 'integer',
        'is_freezed' => 'boolean',
        'is_completed' => 'boolean',
        'is_receiver_completed' => 'boolean',
        'is_hided' => 'boolean',
        'added_by' => 'integer',
        'to_item_id' => 'integer',
        'given_by_item_grams_op' => 'decimal:4',
        'given_to_item_grams_op' => 'decimal:4',
        'given_by_item_purity_op' => 'decimal:4',
        'given_to_item_purity_op' => 'decimal:4',
        'given_by_item_grams_cb' => 'decimal:4',
        'given_to_item_grams_cb' => 'decimal:4',
        'given_by_item_purity_cb' => 'decimal:4',
        'given_to_item_purity_cb' => 'decimal:4',
        'stock_in_ob' => 'decimal:4',
        'stock_in_cb' => 'decimal:4',
        'stock_in_ob_purity' => 'decimal:4',
        'stock_in_cb_purity' => 'decimal:4',
        'retailer_id' => 'integer',
        'retailer_op_grams' => 'decimal:4',
        'retailer_op_purity' => 'decimal:4',
        'to_retailer_id' => 'integer',
        'to_retailer_op_grams' => 'decimal:4',
        'to_retailer_op_purity' => 'decimal:4',
        'reply_history_json' => 'array',
        'obcb_details' => 'array',
        'added_at' => 'datetime',
    ];

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'item_id', 'item_id');
    }

    public function toItem(): BelongsTo
    {
        return $this->belongsTo(Item::class, 'to_item_id', 'item_id');
    }

    public function givenBy(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class, 'given_by', 'user_id');
    }

    public function givenTo(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class, 'given_to', 'user_id');
    }

    public function addedBy(): BelongsTo
    {
        return $this->belongsTo(UserDetail::class, 'added_by', 'user_id');
    }

    public function bill(): BelongsTo
    {
        return $this->belongsTo(BillingEntry::class, 'bill_id', 'bill_id');
    }

    public function stockIn(): BelongsTo
    {
        return $this->belongsTo(StockDetails::class, 'stock_in_id', 'stock_id');
    }

    public function cashTxns(): HasMany
    {
        return $this->hasMany(CashTxn::class, 'stock_id', 'stock_id');
    }

    public function gmsHistoriesOut(): HasMany
    {
        return $this->hasMany(GmsHistory::class, 'gms_stock_out_id', 'stock_id');
    }

    public function gmsHistoriesIn(): HasMany
    {
        return $this->hasMany(GmsHistory::class, 'gms_stock_in_id', 'stock_id');
    }

    public function numericWastages(): HasMany
    {
        return $this->hasMany(NumericWastage::class, 'stock_id', 'stock_id');
    }

    protected static function booted()
    {
        $flushCache = function ($stock) {
            if ($stock->given_by && $stock->given_to) {
                if (Cache::supportsTags()) {
                    Cache::tags(["cash_history_{$stock->given_by}_{$stock->given_to}"])->flush();
                    Cache::tags(["cash_history_{$stock->given_to}_{$stock->given_by}"])->flush();
                }
            }
        };

        static::created($flushCache);
        static::updated($flushCache);
        static::deleted($flushCache);
    }
}
