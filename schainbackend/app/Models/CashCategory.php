<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CashCategory extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'cash_categories';
    protected $primaryKey = 'category_id';

    protected $fillable = [
        'category_name',
        'category_type',
        'is_active',
        'added_by',
        'cash_main_category_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
