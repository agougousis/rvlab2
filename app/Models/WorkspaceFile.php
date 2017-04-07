<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Model;

/**
 * Model to handle database data about workspace files
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class WorkspaceFile extends Model
{
    protected $table = 'workspace_files';
    public $timestamps = false;

    /**
     * Returns the list of workspace files belong to a specific user, ordered
     * by filename
     *
     * @param string $user_email
     * @return array
     */
    public static function getUserFiles($user_email)
    {
        $results = DB::table('workspace_files')
                    ->where('user_email', $user_email)
                    ->orderBy('filename')
                    ->get();

        return $results;
    }
}
