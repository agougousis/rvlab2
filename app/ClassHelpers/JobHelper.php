<?php

namespace App\ClassHelpers;

use Route;
use Validator;
use App\Models\Job;
use App\Models\JobsLog;
use App\Models\SystemLog;
use App\Models\WorkspaceFile;
use App\ClassHelpers\RvlabParser;
use App\Exceptions\InvalidRequestException;

/**
 * Description of DirectoryManager
 *
 * @author Alexandros
 */
class JobHelper
{
    private $workspace_path;
    private $jobs_path;

    public function __construct()
    {
        $this->workspace_path = config('rvlab.workspace_path');
        $this->jobs_path = config('rvlab.jobs_path');
    }

    /**
     * Makes a basic validation to the submitted form.
     *
     * This validation is analysis-agnostic and checks for input that is
     * required for all kinds of analysis.
     *
     * @param array $form
     * @throws InvalidRequestException
     */
    public function basicFormValidation($form)
    {
        $validator = Validator::make($form, [
                    'function' => 'required|string|max:250',
                    'box' => 'required'
        ]);

        if ($validator->fails()) {
            $exception = new InvalidRequestException('Invalid job submission by '.session('user_info.email'));
            $exception->setUserMessage( implode('<br>', $validator->errors()->all()) );
            $exception->enableToastr();
            throw $exception;
        }
    }

    /**
     * Creates user directories in case they do not exist
     *
     * @param string $job_id
     * @param string $user_email
     * @throws \Exception
     */
    public function buildJobDirectories($job_id, $user_email)
    {
        $user_jobs_path = $this->jobs_path . '/' . $user_email;
        $job_folder = $user_jobs_path . '/' . $job_id;
        $user_workspace = $this->workspace_path . '/' . $user_email;

        // Create the required folders if they are not exist
        if (!file_exists($user_workspace)) {
            if (!mkdir($user_workspace)) {
                throw new \Exception('User workspace directory could not be created!');
            }
        }
        if (!file_exists($user_jobs_path)) {
            if (!mkdir($user_jobs_path)) {
                throw new \Exception('User jobs directory could not be created!');
            }
        }

        if (!file_exists($job_folder)) {
            if (!mkdir($job_folder)) {
                throw new \Exception('Job directory could not be created!');
            }
        }
    }

    /**
     * Deletes a job directory
     *
     * @param int $jobId
     * @param string $jobFolder
     * @return null
     */
    public function deleteJob($jobId, $jobFolder)
    {
        // Delete the job record
        $job = Job::where('id', $jobId)->first();

        if (!empty($job)) {
            $job->delete();
        }

        // Delete folder if created
        if (file_exists($jobFolder)) {
            if (!delTree($jobFolder)) {
                $this->log_event('Folder ' . $jobFolder . ' could not be deleted after failed job submission!', "error");
            }
        }
    }

    /**
     * Adds the file record IDs to a string that initially contains only the
     * filenames.
     *
     * @param string $inputs
     * @return string
     */
    public function addIdsToInputFiles($inputs)
    {
        $userInfo = session('user_info');

        $input_ids = array();
        $inputs_list = explode(';', $inputs);
        foreach ($inputs_list as $input) {
            $file_record = WorkspaceFile::whereRaw('BINARY filename LIKE ?', array($input))->where('user_email', $userInfo['email'])->first();
            $input_ids[] = $file_record->id . ":" . $input;
        }
        return implode(';', $input_ids);
    }

    /**
     *
     * @param int $job_id Refreshes the status of a specific job
     *
     * @param int $job_id
     * @return void
     */
    public function refresh_job_status($job_id)
    {
        $job = Job::find($job_id);

        $job_folder = $this->jobs_path . '/' . $job->user_email . '/job' . $job_id;
        $pbs_filepath = $job_folder . '/job' . $job->id . '.pbs';
        $submitted_filepath = $job_folder . '/job' . $job->id . '.submitted';

        if (file_exists($pbs_filepath)) {
            $status = 'submitted';
        } else if (!file_exists($submitted_filepath)) {
            $status = 'creating';
        } else {
            $status_file = $job_folder . '/job' . $job_id . '.jobstatus';
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
                case 'parallel_taxa2dist':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
                case 'parallel_postgres_taxa2dist':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
                case 'parallel_anosim':
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
                default:
                    $fileToParse = '/job' . $job_id . '.Rout';
            }

            // If job has run, check for R errors
            if ($status == 'completed') {
                $parser = new RvlabParser();
                $parser->parse_output($job_folder . $fileToParse);
                if ($parser->hasFailed()) {
                    $status = 'failed';
                    //$this->log_event(implode(' - ',$parser->getOutput()),"info");
                }
            }
        }

        $job->status = $status;
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
            $job_log->parameters = $job->parameters;
            $job_log->save();
        } else if (($status == 'running') && (empty($job_log->started_at))) {
            $job_log->started_at = $job->started_at;
            $job_log->save();
        }
    }

    /**
     * Saves a log to the database
     *
     * @param string $message
     * @param string $category
     */
    protected function log_event($message, $category)
    {

        $db_message = $message;
        $route = explode('@', Route::currentRouteName());

        $log = new SystemLog();
        $log->when = date("Y-m-d H:i:s");
        $log->user_email = session('user_info.email');
        $log->controller = (!empty($route[0])) ? $route[0] : 'unknown';
        $log->method = (!empty($route[0])) ? $route[1] : 'unknown';
        $log->message = $db_message;
        $log->category = $category;
        $log->save();
    }
}
