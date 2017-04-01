<?php

namespace App\Console\Commands;

use App\Models\Job;
use App\Models\JobsLog;
use App\Models\SystemLog;
use App\ClassHelpers\RvlabParser;
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
        $workspace_path = config('rvlab.workspace_path');
        $jobs_path = config('rvlab.jobs_path');

        try {
            // Get list of unfinished jobs
            $pending_jobs = Job::whereNotIn('status', array('failed', 'completed'))->get();

            $counter = 0;
            // Check status flag for each job
            foreach ($pending_jobs as $job) {

                $counter++;
                $job_folder = $jobs_path . '/' . $job->user_email . '/job' . $job->id;
                $pbs_filepath = $job_folder . '/job' . $job->id . '.pbs';
                $submitted_filepath = $job_folder . '/job' . $job->id . '.submitted';
                $started_at = '';
                $completed_at = '';

                if (file_exists($pbs_filepath)) {
                    $status = 'submitted';
                } else if (!file_exists($submitted_filepath)) {
                    $status = 'creating';
                } else {
                    $status_file = $job_folder . '/job' . $job->id . '.jobstatus';
                    $status_info = file($status_file);
                    $status_parts = preg_split('/\s+/', $status_info[0]);
                    $status_message = $status_parts[8];

                    switch ($status_message) {
                        case 'Q':
                            $status = 'queued';
                            break;
                        case 'R':
                            $status = 'running';
                            $started_at = $status_parts[3] . ' ' . $status_parts[4];
                            $completed_at = $status_parts[5] . ' ' . $status_parts[6];
                            break;
                        case 'ended':
                            $status = 'completed';
                            $started_at = $status_parts[3] . ' ' . $status_parts[4];
                            $completed_at = $status_parts[5] . ' ' . $status_parts[6];
                            break;
                        case 'ended_PBS_ERROR':
                            $status = 'failed';
                            $started_at = $status_parts[3] . ' ' . $status_parts[4];
                            $completed_at = $status_parts[5] . ' ' . $status_parts[6];
                            break;
                    }

                    switch ($job->function) {
                        case 'bict':
                            $fileToParse = '/cmd_line_output.txt';
                            break;
                        case 'parallel_anosim':
                            $fileToParse = '/cmd_line_output.txt';
                            break;
                        case 'parallel_taxa2dist':
                            $fileToParse = '/cmd_line_output.txt';
                            break;
                        case 'parallel_postgres_taxa2dist':
                            $fileToParse = '/cmd_line_output.txt';
                            break;
                        case 'parallel_mantel':
                            $fileToParse = '/cmd_line_output.txt';
                            break;
                        case 'parallel_taxa2taxon':
                            $fileToParse = '/cmd_line_output.txt';
                            break;
                        case 'parallel_permanova':
                            $fileToParse = '/cmd_line_output.txt';
                            break;
                        case 'parallel_bioenv':
                            $fileToParse = '/cmd_line_output.txt';
                            break;
                        case 'parallel_simper':
                            $fileToParse = '/cmd_line_output.txt';
                            break;
                        case 'phylobar':
                            $fileToParse = '';
                        default:
                            $fileToParse = '/job' . $job->id . '.Rout';
                    }

                    // If job has run, check for R errors
                    if (($status == 'completed') && !$fileToParse) {
                        $parser = new RvlabParser();
                        $parser->parse_output($job_folder . $fileToParse);
                        if ($parser->hasFailed()) {
                            $status = 'failed';
                        }
                    }
                }

                $job->status = $status;
                $job->jobsize = directory_size($job_folder);
                if (!empty($started_at)) {
                    $job->started_at = $started_at;
                }
                if (!empty($completed_at)) {
                    $job->completed_at = $completed_at;
                }
                $job->save();

                // IF job was completed successfully use it for statistics
                if ($status == 'completed') {

                    $job_log = new JobsLog();
                    $job_log->id = $job->id;
                    $job_log->user_email = $job->user_email;
                    $job_log->function = $job->function;
                    $job_log->status = $job->status;
                    $job_log->submitted_at = $job->submitted_at;
                    $job_log->started_at = $job->started_at;
                    $job_log->completed_at = $job->completed_at;
                    $job_log->jobsize = $job->jobsize;
                    $job_log->inputs = $job->inputs;
                    $job_log->save();
                } else if (($status == 'running') && (empty($job_log->started_at))) {
                    $job_log->started_at = $job->started_at;
                    $job_log->save();
                }
            }

        } catch (Exception $ex) {
            $this->save_log($ex->getMessage(), 'error');
        }
    }

    /**
     * Logs a message to database
     *
     * @param type $message
     * @param type $category
     */
    private function save_log($message, $category)
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
