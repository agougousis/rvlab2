<?php

namespace App\Http\Controllers;

use DB;
use Response;
use App\Models\Job;
use App\ClassHelpers\JobHelper;
use App\ClassHelpers\ConditionsChecker;
use App\Http\Controllers\CommonController;

class JobAjaxController extends CommonController
{
    /**
     * A helper object that handles subtasks related to R vLab job management
     *
     * @var JobHelper
     */
    protected $jobHelper;

    /**
     * An helper object that is used to check for necessery conditions
     *
     * @var ConditionsChecker
     */
    private $conditionChecker;

    public function __construct(JobHelper $directoryManager)
    {
        parent::__construct();

        $this->jobHelper = $directoryManager;

        $this->conditionChecker = new ConditionsChecker($this->jobs_path, $this->workspace_path);
        $this->conditionChecker->checkStorage();
    }

    /**
     * Retrieves the list of jobs in user's workspace
     *
     * @return JSON
     */
    public function getUserJobs()
    {
        $userInfo = session('user_info');

        if (!empty($userInfo)) {
            $user_email = $userInfo['email'];
            $timezone = $userInfo['timezone'];
            $job_list = Job::where('user_email', $user_email)->orderBy('id', 'desc')->get();
            foreach ($job_list as $job) {
                $job->submitted_at = dateToTimezone($job->submitted_at, $timezone);
                $job->started_at = dateToTimezone($job->started_at, $timezone);
                $job->completed_at = dateToTimezone($job->completed_at, $timezone);
            }
            $json_list = $job_list->toArray();
            return Response::json($json_list, 200);
        } else {
            return Response::json(array('message' => 'You are not logged in or your session has expired!'), 401);
        }
    }

    /**
     * Retrieves the status of a submitted job
     *
     * @param int $job_id
     * @return JSON
     */
    public function getJobStatus($job_id)
    {
        $userInfo = session('user_info');
        $user_email = $userInfo['email'];

        // Check if this job belongs to this user
        $result = DB::table('jobs')
                ->where('id', $job_id)
                ->where('user_email', $user_email)
                ->first();

        if (empty($result)) {
            $this->logEvent("Trying to retrieve status for a job that does not belong to this user.", "unauthorized");
            $response = array('message', 'Trying to retrieve status for a job that does not belong to this user!');
            return Response::json($response, 401);
        }

        return Response::json(array('status' => $result->status), 200);
    }

    /**
     * Retrieves the R script used in the execution of a submitted job.
     *
     * @param int $job_id
     * @return JSON
     */
    public function getRScript($job_id)
    {
        $userInfo = session('user_info');
        $user_email = $userInfo['email'];
        $job_folder = $this->jobs_path . '/' . $user_email . '/job' . $job_id;
        $fullpath = $job_folder . '/job' . $job_id . '.R';

        // Check if the R script exists
        if (!file_exists($fullpath)) {
            $this->logEvent("Trying to retrieve non existent R script.", "illegal");
            $response = array('message', 'Trying to retrieve non existent R script!');
            return Response::json($response, 400);
        }

        // Check if this job belongs to this user
        $result = DB::table('jobs')
                ->where('id', $job_id)
                ->where('user_email', $user_email)
                ->first();

        if (empty($result)) {
            $this->logEvent("Trying to retrieve an R script from a job that does not belong to this user.", "unauthorized");
            $response = array('message', 'Trying to retrieve an R script from a job that does not belong to this user!');
            return Response::json($response, 401);
        }

        $r = file($fullpath);
        return Response::json($r, 200);
    }
}
