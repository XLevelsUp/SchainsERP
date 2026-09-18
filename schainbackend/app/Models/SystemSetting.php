<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SystemSetting extends Model
{
    protected $table = 'system_settings';
    
    public $timestamps = true;

    protected $fillable = [
        'setting_key',
        'setting_value',
        'description',
    ];

    protected $casts = [
        'setting_value' => 'array',
    ];

    /**
     * Get a setting value by key, optionally providing a default.
     */
    public static function get(string $key, $default = null)
    {
        $setting = self::where('setting_key', $key)->first();
        return $setting ? $setting->setting_value : $default;
    }
}
