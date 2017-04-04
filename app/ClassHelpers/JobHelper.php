<?php

namespace App\ClassHelpers;

use Route;
use Validator;
use App\Models\Job;
use App\Models\JobsLog;
use App\Models\SystemLog;
use App\Models\WorkspaceFile;
use App\ClassHelpers\JobOutputParser;
use App\ClassHelpers\JobStatusParser;
use App\Exceptions\InvalidRequestException;

/**
 * Handles subtasks related to R vLab jobs
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class JobHelper
{
    /**
     * The directory path to R vLab workspace
     *
     * @var string
     */
    private $workspace_path;

    /**
     * The directory path to R vLab jobs
     *
     * @var string
     */
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
            $exception->setUserMessage(implode('<br>', $validator->errors()->all()));
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
     * @param Job $jobId
     * @param string $jobFolder
     * @return null
     */
    public function deleteJob(Job $job)
    {
        $user_email = session('user_info.email');
        $job_folder = $this->jobs_path . '/' . $user_email . '/job' . $job->id;

        // Delete the job record
        if (!empty($job)) {
            $job->delete();
        }

        // Delete folder if created
        if (file_exists($job_folder)) {
            if (!delTree($job_folder)) {
                $this->logEvent('Folder ' . $job_folder . ' could not be deleted after failed job submission!', "error");
                return false;
            }
        }

        return true;
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
     * Updates the job status
     *
     * This method should never be called for a job with database status
     * equal to completed.
     *
     * @param Job $job
     * @return void
     */
    public function refreshJobStatus(Job $job)
    {
        $job_folder = $this->jobs_path . '/' . $job->user_email . '/job' . $job->id;
        $pbs_filepath = $job_folder . '/job' . $job->id . '.pbs';
        $submitted_filepath = $job_folder . '/job' . $job->id . '.submitted';

        if (file_exists($pbs_filepath)) {
            $status = 'submitted';
        } elseif (!file_exists($submitted_filepath)) {
            $status = 'creating';
        } else {
            $statusFilePath = $job_folder . '/job' . $job->id . '.jobstatus';

            // Update the job status parsing the job status file
            // We assume an initial status of 'submitted' but this should change
            // under normal conditions.
            list($status, $started_at, $completed_at) = JobStatusParser::parseStatusFile($statusFilePath, 'submitted');

            // File to parse for errors
            $outputFileToParse = JobStatusParser::outputFileToParse($job->function, $job->id);

            // If job has run, check for R errors
            if ($status == 'completed') {
                $parser = new JobOutputParser();

                $parser->parseOutput($job_folder . $outputFileToParse);

                if ($parser->hasFailed()) {
                    $status = 'failed';
                }
            }

            if (!empty($started_at)) {
                $job->started_at = $started_at;
            }
            if (!empty($completed_at)) {
                $job->completed_at = $completed_at;
            }
        }

        $job->jobsize = directory_size($job_folder);
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
        }
    }

    /**
     * Returns a download response for a job file
     *
     * @param string $filepath
     * @param int $job_id
     * @return ResponseFactory
     */
    public function downloadJobFile($filepath, $job_id)
    {
        $parts = pathinfo(basename($filepath));

        // Download the file with a name that contains the job ID
        $new_filename = $parts['filename'] . '_job' . $job_id . '.' . $parts['extension'];

        switch ($parts['extension']) {
            case 'png':
                return response()->download($filepath, $new_filename, ['Content-Type' => 'image/png']);
            case 'csv':
                return response()->download($filepath, $new_filename, ['Content-Type' => 'text/plain', 'Content-Disposition' => 'attachment; filename=' . $new_filename]);
            case 'nwk':
            case 'pdf':
                return response()->download($filepath, $new_filename, ['Content-Type' => 'application/octet-stream']);
        }
    }

    /**
     * Saves a log to the database
     *
     * @param string $message
     * @param string $category
     */
    protected function logEvent($message, $category)
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
