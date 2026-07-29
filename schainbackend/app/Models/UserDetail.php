<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserDetail extends Model
{
    use HasFactory;

    protected $table = 'user_details';

    protected $primaryKey = 'user_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [

        // User details
        'name',
        'user_name',
        'password_hash',
        'address',
        'signature',
        'code',
        'phone_no',
        'remarks',
        'proff',
        'role_id',
        'customer_commants',
        'mailing_name',
        'category_name',
        'system_id',

        // Images
        'profile_image',
        'aadhar_image',

        // Status
        'is_active',
        'is_delete',
        'is_billable',

        // User settings
        'is_customerfitem_cal_enabled',
        'is_customerfitem_cal_in_enabled',
        'is_create_order_shown',
        'is_salary_person',
        'is_gold_cal_enabled',
        'is_cash_cal_enabled',
        'is_wastage_cal_enabled',
        'is_otp_verified',

        // Dates
        'added_at',
        'updated_at',
    ];

    protected $casts = [

        'role_id' => 'integer',

        'is_active' => 'boolean',
        'is_delete' => 'boolean',
        'is_billable' => 'boolean',

        'is_customerfitem_cal_enabled' => 'boolean',
        'is_customerfitem_cal_in_enabled' => 'boolean',
        'is_create_order_shown' => 'boolean',
        'is_salary_person' => 'boolean',
        'is_gold_cal_enabled' => 'boolean',
        'is_cash_cal_enabled' => 'boolean',
        'is_wastage_cal_enabled' => 'boolean',
        'is_otp_verified' => 'boolean',

        'added_at' => 'datetime',
        'updated_at' => 'datetime',
    ];


    /*
    |--------------------------------------------------------------------------
    | ITEM MAPPINGS
    |--------------------------------------------------------------------------
    */

    public function itemMappings()
    {
        return $this->hasMany(
            UsersItemsMapping::class,
            'user_id',
            'user_id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | HEAD EMPLOYEE MAPPINGS
    |--------------------------------------------------------------------------
    */

    public function headEmployeeMappings()
    {
        return $this->hasMany(
            HeadEmployeeMapping::class,
            'employee_id',
            'user_id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | CASH HEAD EMPLOYEE MAPPINGS
    |--------------------------------------------------------------------------
    */

    public function cashHeadEmployeeMappings()
    {
        return $this->hasMany(
            CashHeadEmployeeMapping::class,
            'employee_id',
            'user_id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | ROLE
    |--------------------------------------------------------------------------
    */

    public function role()
    {
        return $this->belongsTo(
            Role::class,
            'role_id',
            'id'
        );
    }


    /*
    |--------------------------------------------------------------------------
    | PROFILE IMAGE URL
    |--------------------------------------------------------------------------
    */

    public function getProfileImageUrlAttribute()
    {
        if (!$this->profile_image) {
            return null;
        }

        return asset(
            'storage/' . $this->profile_image
        );
    }


    /*
    |--------------------------------------------------------------------------
    | AADHAR IMAGE URL
    |--------------------------------------------------------------------------
    */

    public function getAadharImageUrlAttribute()
    {
        if (!$this->aadhar_image) {
            return null;
        }

        return asset(
            'storage/' . $this->aadhar_image
        );
    }
}