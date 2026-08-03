<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class WastageDetails extends Model
{
    protected $table = 'wastage_details';

    protected $primaryKey = 'waste_id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'waste_name',
        'waste_value',
    ];

    protected $casts = [
        'waste_value' => 'string',
    ];
}
