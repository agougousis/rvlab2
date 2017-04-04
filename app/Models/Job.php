<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Job extends Model
{
    protected $table = 'jobs';
    public $timestamps = false;

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
}
