<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Model to handle database data about jobs
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class Job extends Model
{
    protected $table = 'jobs';
    public $timestamps = false;

    /**
     * Returns a list of old jobs
     *
     * A list of jobs that should be deleted because they have being
     * stored/retained longer than intended by R vLab.
     *
     * @return Collection
     */
    public static function getOldJobs()
    {
        $job_max_storagetime = Setting::where('sname', 'job_max_storagetime')->first(); // should be in days

        $start_date = new \DateTime();
        $start_date->sub(new \DateInterval('P' . $job_max_storagetime->value . 'D'));

        $old_jobs = Job::whereNotNull('completed_at')
                ->where('completed_at', '<=', $start_date->format('Y-m-d H:i:s'))
                ->get();

        return $old_jobs;
    }

    /**
     * Returns the last N submitted jobs
     *
     * @param int $n
     * @return Collection
     */
    public static function getLastN($n)
    {
        return Job::orderBy('submitted_at', 'desc')->take($n)->get();
    }
}
