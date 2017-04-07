<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model to handle database data about system logs
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class SystemLog extends Model
{
    protected $table = 'logs';
    public $timestamps = false;

    /**
     * Returns the last N errors that happened
     *
     * @param int $n
     * @return Collection
     */
    public static function getLastErrors($n)
    {
        return SystemLog::where('category', 'error')->orderBy('when', 'desc')->take($n->value)->get();
    }
}
