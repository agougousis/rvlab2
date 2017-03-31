<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Model;

class WorkspaceFile extends Model
{
    protected $table = 'workspace_files';
    public $timestamps = false;

    static function getUserFiles($user_email){
        $results = DB::table('workspace_files')
                    ->where('user_email',$user_email)
                    ->orderBy('filename')
                    ->get();
        return $results;
    }

}
