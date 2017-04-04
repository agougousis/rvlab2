<?php

namespace App\Console\Commands;

use App\Models\Job;
use App\Models\SystemLog;
use App\ClassHelpers\JobHelper;
use Illuminate\Console\Command;

/**
 * A period task that updates the status of unfinished jobs
 *
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class RefreshStatus extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'jobs:refreshStatus';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Refreshes the status of unfinished jobs';

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
        try {
            // Get list of unfinished jobs
            $pending_jobs = Job::whereNotIn('status', array('failed', 'completed'))->get();

            $counter = 0;
            // Check status flag for each job
            foreach ($pending_jobs as $job) {
                $counter++;

                $jobHelper = new JobHelper();
                $jobHelper->refreshJobStatus($job);
            }
        } catch (\Exception $ex) {
            $this->save_log($ex->getMessage(), 'error');
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
        $log->controller = 'Laravel Command';
        $log->method = 'RefreshStatusCommand';
        $log->message = $message;
        $log->category = $category;
        $log->save();
    }
}
