<?php

namespace App\Console\Commands;

use App\Models\Job;
use App\Models\SystemLog;
use Illuminate\Console\Command;

/**
 * A period task that enforces the storage policy
 *
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class RemoveOldJobs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:removeOld';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $jobs_path = config('rvlab.jobs_path');
        $old_jobs = Job::getOldJobs();
        $counter = 0;

        foreach ($old_jobs as $job) {
            try {
                // Delete job files
                $job_folder = $jobs_path . '/' . $job->user_email . '/job' . $job->id;
                if (!delTree($job_folder)) {
                    $this->saveLog('Folder ' . $job_folder . ' could not be deleted!', "error");
                }

                // Delete job record
                $job->delete();

                $counter++;
            } catch (\Exception $ex) {
                $this->saveLog("Error occured during deletion of job" . $job->id . ". Message: " . $ex->getMessage(), "error");
            }
        }
    }

    /**
     * Logs a message to database
     *
     * @param type $message
     * @param type $category
     */
    private function saveLog($message, $category)
    {
        $log = new SystemLog();
        $log->when = date("Y-m-d H:i:s");
        $log->user_email = 'system';
        $log->controller =  'Laravel Command';
        $log->method = 'RemoveOldJobsCommand';
        $log->message = $message;
        $log->category = $category;
        $log->save();
    }
}
