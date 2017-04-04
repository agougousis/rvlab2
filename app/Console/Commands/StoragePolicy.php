<?php

namespace App\Console\Commands;

use App\Models\Job;
use App\Models\Setting;
use App\Models\SystemLog;
use App\Models\WorkspaceFile;
use Illuminate\Console\Command;

/**
 * A period task that enforces the storage policy
 *
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class StoragePolicy extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'storage:enforcePolicy';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Enforces R vLab storage policy';

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
        // Calculate storage utilization
        $rvlab_storage_limit = Setting::where('name', 'rvlab_storage_limit')->first();

        $jobs_path = config('rvlab.jobs_path');
        $jobs_size = directory_size($jobs_path); // in KB

        $workspace_path = config('rvlab.workspace_path');
        $workspace_size = directory_size($workspace_path); // in KB

        $used_size = $jobs_size + $workspace_size;
        $utilization = 100 * $used_size / $rvlab_storage_limit->value;

        // If we are running out of space, delete jobs from users that have
        // exceeded their personal limit
        $max_users_suported = Setting::where('name', 'max_users_suported')->first();
        $user_soft_limit = $rvlab_storage_limit->value / $max_users_suported->value;

        if ($utilization > 10) {
            // Find R vLab active users
            $users_with_inputs = WorkspaceFile::select('user_email')->distinct()->get()->toArray(); // Get users with a least one input file
            $users_with_jobs = Job::select('user_email')->distinct()->get()->toArray(); // Get users with a least one job
            $iu = flatten($users_with_inputs);
            $ju = flatten($users_with_jobs);
            $active_users = array_unique(array_merge($iu, $ju));

            foreach ($active_users as $user_email) {
                $jobs_size = directory_size($jobs_path . '/' . $user_email); // in KB
                $workspace_size = directory_size($workspace_path . '/' . $user_email); // in KB
                // If user has exceeded his soft limit
                if (($jobs_size + $workspace_size) > $user_soft_limit) {
                    // Get user's jobs
                    $jobs = Job::where('user_email', $user_email)->whereIn('status', array('completed', 'failed'))->orderBy('submitted_at', 'asc')->get();
                    // Delete jobs until user does not exceed his soft limit
                    // (delete jobs from oldest to newer)
                    foreach ($jobs as $job) {
                        // Delete the job
                        try {
                            $job_id = $job->id;

                            // Delete job record
                            $job->delete();

                            // Delete job files
                            $job_folder = $jobs_path . '/' . $user_email . '/job' . $job_id;
                            if (!delTree($job_folder)) {
                                $this->saveLog('Folder ' . $job_folder . ' could not be deleted!', "error");
                            }
                            $this->saveLog('Folder deleted - Job ID: ' . $job_id . ' - User: ' . $user_email, "info");
                        } catch (\Exception $ex) {
                            $this->saveLog("Error occured during deletion of job" . $job_id . ". Message: " . $ex->getMessage(), "error");
                        }

                        // Check if user still exceeds its soft limit
                        $new_jobs_size = directory_size($jobs_path . '/' . $user_email); // in KB
                        if (($new_jobs_size + $workspace_size) <= $user_soft_limit) {
                            break;
                        }
                    }
                }
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
        $log->controller = 'Laravel Command';
        $log->method = 'StorageUtilizationCommand';
        $log->message = $message;
        $log->category = $category;
        $log->save();
    }
}
