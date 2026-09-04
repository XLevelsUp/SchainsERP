<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeadEmployeeMapping extends Model
{
    protected $table = 'head_employee_mappings';

    protected $primaryKey = 'id';

    public $incrementing = true;

    protected $keyType = 'int';

    public $timestamps = false;

    protected $fillable = [
        'head_id',
        'employee_id',
        'added_at',
    ];

    protected $casts = [
        'id' => 'integer',
        'head_id' => 'integer',
        'employee_id' => 'integer',
        'added_at' => 'datetime',
    ];

    public function head()
    {
        return $this->belongsTo(UserDetail::class, 'head_id', 'user_id');
    }

    public function employee()
    {
        return $this->belongsTo(UserDetail::class, 'employee_id', 'user_id');
    }
}