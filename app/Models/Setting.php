<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model to handle database data about system settings
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class Setting extends Model
{
    protected $table = 'settings';
    public $timestamps = false;

    /**
     * Returns all system settings as an assosiative array
     *
     * @return array
     */
    public static function getAllSettings()
    {
        $settingsSet = DB::table('settings')->select('name', 'value')->get();
        $settings = array();
        foreach ($settingsSet as $setting) {
            $settings[$setting->name] = $setting->value;
        }
        return $settings;
    }

    /**
     * Updates some of the system settings with the new provided values
     *
     * @param array $newSettings
     */
    public static function updateAll($newSettings)
    {
        foreach ($newSettings as $key => $value) {
            self::updateItem($key, $value);
        }
    }

    /**
     * Updates the value of a specific setting
     *
     * @param string $key
     * @param string $newValue
     */
    public static function updateItem($key, $newValue)
    {
        $setting = Setting::where('sname', $key)->first();
        if (!empty($setting)) {
            $setting->value = $newValue;
            $setting->last_modified = (new \DateTime())->format("Y-m-d H:i:s");
            $setting->save();
        }
    }
}
