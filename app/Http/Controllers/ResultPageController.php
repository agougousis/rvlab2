<?php

namespace App\Http\Controllers;

use Session;
use Redirect;
use Response;
use App\Models\Job;
use App\Models\WorkspaceFile;
use App\ClassHelpers\JobHelper;
use App\ClassHelpers\ResultHelper;
use App\ClassHelpers\JobOutputParser;
use App\ClassHelpers\ConditionsChecker;

/**
 * ....
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class ResultPageController extends CommonController
{
    /**
     * A helper object that handles subtasks related to R vLab job management
     *
     * @var JobHelper
     */
    protected $jobHelper;

    protected $resultHelper;

    /**
     * A helper object that is used to check for necessery conditions
     *
     * @var ConditionsChecker
     */
    private $conditionChecker;

    public function __construct(JobHelper $directoryManager, ResultHelper $resultHelper)
    {
        parent::__construct();

        $this->jobHelper = $directoryManager;
        $this->resultHelper = $resultHelper;

        $this->conditionChecker = new ConditionsChecker($this->jobs_path, $this->workspace_path);
        $this->conditionChecker->checkStorage();
    }

    /**
     * Displays the results page of a job
     *
     * @param int $job_id
     * @return View|JSON
     */
    public function jobPage($job_id)
    {
        $user_email = session('user_info.email');

        $data['job'] = $job = Job::where('user_email', $user_email)->where('id', $job_id)->first();

        // In case job id wasn't found
        if (empty($job)) {
            return $this->jobIdNotFoundResponse();
        }

        // Load information about input files
        $inputs = $this->getInputFilesList($job);

        // If job execution has not finished, try to update its status
        if (in_array($job->status, array('submitted', 'running', 'queued'))) {
            $this->jobHelper->refreshJobStatus($job);
        }

        $data['function'] = $job->function;

        // If job has failed
        if ($job->status == 'failed') {
            $this->loadPotentialErrorMessages($job, $data);

            // Send back information about possible error messages/output
            if ($this->is_mobile) {
                $response = array('message', 'Error occured during submission.');
                return Response::json($response, 500);
            } else {
                return $this->loadView('results/failed', 'Job Results', $data);
            }
        }

        // If job is pending
        if (in_array($job->status, array('submitted', 'queued', 'running'))) {
            if ($this->is_mobile) {
                $response = array('data', $data);
                return Response::json($response, 500);
            } else {
                $data['refresh_rate'] = $this->system_settings['status_refresh_rate_page'];
                return $this->loadView('results/submitted', 'Job Results', $data);
            }
        }

        $job_folder = $this->jobs_path . '/' . $user_email . '/job' . $job_id;

        // Build the result page for this job
        return $this->buildResultPage($job, $job_folder, $inputs);
    }

    /**
     * Creates an appropriate response for a job ID that does not exist
     *
     * @return mixed
     */
    private function jobIdNotFoundResponse()
    {
        if ($this->is_mobile) {
            $response = array('message', 'You have not submitted recently any job with such ID!');
            return Response::json($response, 400);
        } else {
            Session::flash('toastr', array('error', 'You have not submitted recently any job with such ID!'));
            return Redirect::back();
        }
    }

    /**
     * Search for error messages in a failed job
     *
     * @param Job $job
     * @param array $data
     */
    protected function loadPotentialErrorMessages(Job $job, array &$data)
    {
        $job_folder = $this->jobs_path . '/' . $job->user_email . '/job' . $job->id;
        $log_file = $job_folder . "/job" . $job->id . ".log";

        // Decide which file should be parsed for messages
        switch ($job->function) {
            case 'simper':
            case 'bict':
            case 'parallel_anosim':
            case 'parallel_taxa2dist':
            case 'parallel_postgres_taxa2dist':
            case 'parallel_mantel':
            case 'parallel_taxa2taxon':
            case 'parallel_permanova':
            case 'parallel_bioenv':
            case 'parallel_simper':
                $fileToParseForErrors = '/cmd_line_output.txt';
                break;
            default:
                $fileToParseForErrors = '/job' . $job->id . '.Rout';
        }

        // Parse the file
        $parser = new JobOutputParser();
        $parser->parseOutput($job_folder . $fileToParseForErrors);

        // We should also look for messages in the job's log file
        if ($parser->hasFailed()) {
            $data['errorString'] = implode("<br>", $parser->getOutput());
            $data['errorString'] .= $parser->parseLog($log_file);
        } else {
            $data['errorString'] = "Error occured during submission.";
            $data['errorString'] .= $parser->parseLog($log_file);
        }
    }

    /**
     * Returns a list of input filenames that were used for a submitted job
     *
     * @param Job $job
     * @return array
     */
    protected function getInputFilesList(Job $job)
    {
        $inputs = array();

        $input_files = explode(';', $job->inputs);

        foreach ($input_files as $ifile) {
            $info = explode(':', $ifile);
            $id = $info[0];
            $filename = $info[1];
            $record = WorkspaceFile::where('id', $id)->first();

            if (empty($record)) {
                $exists = false;
            } else {
                $exists = true;
            }

            $inputs[] = array(
                'id' => $id,
                'filename' => $filename,
                'exists' => $exists
            );
        }

        return $inputs;
    }

    /**
     * Builds the job results page
     *
     * @param Job $job
     * @param string $job_folder
     * @param array $input_files
     * @return mixed
     */
    private function buildResultPage(Job $job, $job_folder, array $input_files)
    {
        $data = array();

        $data['function'] = $job->function;
        $data['job'] = $job;
        $data['input_files'] = $input_files;
        $data['job_folder'] = $job_folder;

        $this->resultHelper->loadMainResultFilename($job->function, $data);
        $this->resultHelper->loadResultImages($job->function, $data);

        if (!$this->resultHelper->loadResultHtml($job, $data)) {
            if ($this->is_mobile) {
                return array('data', $data);
            } else {
                return $this->loadView('results/failed', 'Job Results', $data);
            }
        }

        if ($this->is_mobile) {
            unset($data['content']);
            return Response::json(array('data', $data), 200);
        } else {
            return $this->loadView('results/completed', 'Job Results', $data);
        }
    }
}
