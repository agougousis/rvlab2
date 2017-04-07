<?php

namespace App\Models;

use DB;
use Illuminate\Database\Eloquent\Model;

/**
 * Model to handle database data about job logs
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class JobsLog extends Model
{
    protected $table = 'jobs_logs';
    public $timestamps = false;

    /**
     * We just count how many jobs are there with jobsize of x digits for a
     * specific date and on (how many from 1-9 KB, how many from 10-99 KB, etc)
     *
     * @param string $startFromDate
     * @return array
     */
    public static function countBySizeScale($startFromDate)
    {
        return JobsLog::select(DB::raw('count(*) as total, LENGTH(jobsize) AS digits'))
                        ->where('submitted_at', '>', $startFromDate)
                        ->groupBy('digits')
                        ->get()->toArray();
    }

    /**
     * Count how many submissions have been done for each function from a
     * specific date and on
     *
     * @param string $startFromDate
     * @return array
     */
    public static function countFunctionUsage($startFromDate)
    {
        return JobsLog::select(DB::raw('count(*) as total,function'))
                        ->where('submitted_at', '>', $startFromDate)
                        ->groupBy('function')
                        ->get()->toArray();
    }
}
