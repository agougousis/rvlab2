<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    protected $table = 'settings';
    public $timestamps = false;

    public static function getAllSettings(){
        $settingsSet = DB::table('settings')->select('name','value')->get();
        $settings = array();
        foreach($settingsSet as $setting){
            $settings[$setting->name] = $setting->value;
        }
        return $settings;
    }

    public static function updateAll($newSettings){
        foreach($newSettings as $key => $value){
            self::updateItem($key, $value);
        }
    }

    public static function updateItem($key, $newValue) {
        $setting = Setting::where('sname',$key)->first();
        if(!empty($setting)){
            $setting->value = $newValue;
            $setting->last_modified = (new \DateTime())->format("Y-m-d H:i:s");
            $setting->save();
        }
    }
}