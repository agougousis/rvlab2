<?php

namespace App\Http\Controllers;

use DB;
use View;
use Session;
use Redirect;
use Response;
use Validator;
use App\Models\Job;
use App\Models\WorkspaceFile;
use App\ClassHelpers\JobHelper;
use App\Http\Controllers\CommonController;
use Illuminate\Http\Request;

/**
 * Implements functionality related to job submission, job status refreshing and building the results page.
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class JobController extends CommonController
{
    protected $errorMessage = '';
    protected $workspace_path;
    protected $jobs_path;
    protected $remote_jobs_path;
    protected $remote_workspace_path;
    protected $jobHelper;

    public function __construct(JobHelper $directoryManager)
    {
        parent::__construct();

        $this->jobHelper = $directoryManager;

        $this->workspace_path = config('rvlab.workspace_path');
        $this->jobs_path = config('rvlab.jobs_path');
        $this->remote_jobs_path = config('rvlab.remote_jobs_path');
        $this->remote_workspace_path = config('rvlab.remote_workspace_path');

        // Check if cluster storage has been mounted to web server
        if (!$this->check_storage()) {
            if ($this->is_mobile) {
                $response = array('message', 'Storage not found');
                return Response::json($response, 500);
                die();
            } else {
                echo $this->load_view('errors/unmounted', 'Storage not found');
                die();
            }
        }
    }

    /**
     * Displays the Home Page
     *
     * @return View
     */
    public function index()
    {
        $userInfo = session('user_info');
        $user_email = $userInfo['email'];

        $job_list = DB::table('jobs')->where('user_email', $user_email)->orderBy('id', 'desc')->get();
        $r_functions = config('r_functions.list');
        $form_data['workspace_files'] = WorkspaceFile::getUserFiles($user_email);

        $form_data['user_email'] = $user_email;
        $form_data['tooltips'] = config('tooltips');

        if ($this->is_mobile) {
            $mobile_functions = config('mobile_functions.list');
            $response = array(
                'r_functions' => $mobile_functions,
                'job_list' => $job_list,
                'workspace_files' => $form_data['workspace_files'],
            );
            return Response::json($response, 200);
        } else {

            $forms = array();
            foreach ($r_functions as $codename => $title) {
                $forms[$codename] = View::make('forms.' . $codename, $form_data);
            }

            $data['forms'] = $forms;
            $data['job_list'] = $job_list;
            $data['r_functions'] = $r_functions;
            $data2['workspace_files'] = $form_data['workspace_files'];
            $data['count_workspace_files'] = $form_data['workspace_files']->count();
            $data['workspace'] = View::make('workspace.manage', $data2);
            $data['is_admin'] = session('is_admin');
            $data['refresh_rate'] = $this->system_settings['status_refresh_rate_page'];
            $data['timezone'] = $userInfo['timezone'];
            //$data['refresh_rate'] = $this->system_settings['status_refresh_rate_page'];
            // Check if this we load the page after delete_many_jobs() has been called
            if (Session::has('deletion_info')) {
                $data['deletion_info'] = Session::get('deletion_info');
            }

            //
            if (Session::has('workspace_tab_status')) {
                $data['workspace_tab_status'] = Session::get('workspace_tab_status');
            } else {
                $data['workspace_tab_status'] = 'closed';
            }

            //
            if (Session::has('last_function_used')) {
                $data['last_function_used'] = Session::get('last_function_used');
            } else {
                $data['last_function_used'] = "taxa2dist";
            }

            return $this->load_view('index', 'R vLab Home Page', $data);
        }
    }

    /**
     *
     * @return \RedirectResponseDeletes selected jobs
     *
     * @return RedirectResponse
     */
    public function delete_many_jobs(Request $request)
    {
        $form = $request->all();

        if (!empty($form['jobs_for_deletion'])) {
            $job_list_string = $form['jobs_for_deletion'];
            $job_list = explode(';', $job_list_string);

            $total_success = true;
            $error_messages = array();
            $count_deleted = 0;

            foreach ($job_list as $job_id) {
                $result = $this->delete_one_job($job_id);
                if ($result['deleted']) {
                    $count_deleted++;
                } else {
                    $total_success = false;
                    $error_messages[] = $result['message'];
                }
            }

            $deletion_info = array(
                'total' => count($job_list),
                'deleted' => $count_deleted,
                'messages' => $error_messages
            );

            if ($this->is_mobile) {
                return Response::json($deletion_info, 200);
            } else {
                return Redirect::to('/')->with('deletion_info', $deletion_info);
            }
        } else {
            return Redirect::to('/');
        }
    }

    /**
     * Deletes a specific job
     *
     * @param int $job_id
     * @return array
     */
    protected function delete_one_job($job_id)
    {
        $userInfo = session('user_info');
        $job = Job::find($job_id);
        $user_email = $userInfo['email'];

        // Check if this job exists
        if (empty($job)) {
            $this->log_event("User tried to delete a job that does not exist.", "illegal");
            return array(
                'deleted' => false,
                'message' => 'You have tried to delete a job (' . $job_id . ') that does not exist'
            );
        }

        // Check if this job belongs to this user
        if ($job->user_email != $user_email) {
            $this->log_event("User tried to delete a job that does not belong to him.", "unauthorized");
            return array(
                'deleted' => false,
                'message' => 'You have tried to delete a job that does not belong to you.'
            );
        }

        // Check if the job has finished running
        if (in_array($job->status, array('running', 'queued', 'submitted'))) {
            $this->log_event("User tried to delete a job that is not finished.", "illegal");
            return array(
                'deleted' => false,
                'message' => 'You have tried to delete a job (' . $job_id . ') that is not finished.'
            );
        }

        try {
            // Delete job record
            Job::where('id', $job_id)->delete();

            // Delete job files
            $job_folder = $this->jobs_path . '/' . $user_email . '/job' . $job_id;
            if (!delTree($job_folder)) {
                $this->log_event('Folder ' . $job_folder . ' could not be deleted!', "error");
                return array(
                    'deleted' => false,
                    'message' => 'Unexpected error occured during job folder deletion (' . $job_id . ').'
                );
            }

            return array(
                'deleted' => true,
                'message' => ''
            );
        } catch (Exception $ex) {
            $this->log_event("Error occured during deletion of job" . $job_id . ". Message: " . $ex->getMessage(), "error");
            return array(
                'deleted' => false,
                'message' => 'Unexpected error occured during deletion of a job (' . $job_id . ').'
            );
        }
    }

    /**
     * Retrieves the list of jobs in user's workspace
     *
     * @return JSON
     */
    public function get_user_jobs()
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
    public function get_job_status($job_id)
    {
        $userInfo = session('user_info');
        $user_email = $userInfo['email'];

        // Check if this job belongs to this user
        $result = DB::table('jobs')
                ->where('id', $job_id)
                ->where('user_email', $user_email)
                ->first();

        if (empty($result)) {
            $this->log_event("Trying to retrieve status for a job that does not belong to this user.", "unauthorized");
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
    public function get_r_script($job_id)
    {
        $userInfo = session('user_info');
        $user_email = $userInfo['email'];
        $job_folder = $this->jobs_path . '/' . $user_email . '/job' . $job_id;
        $fullpath = $job_folder . '/job' . $job_id . '.R';

        // Check if the R script exists
        if (!file_exists($fullpath)) {
            $this->log_event("Trying to retrieve non existent R script.", "illegal");
            $response = array('message', 'Trying to retrieve non existent R script!');
            return Response::json($response, 400);
        }

        // Check if this job belongs to this user
        $result = DB::table('jobs')
                ->where('id', $job_id)
                ->where('user_email', $user_email)
                ->first();

        if (empty($result)) {
            $this->log_event("Trying to retrieve an R script from a job that does not belong to this user.", "unauthorized");
            $response = array('message', 'Trying to retrieve an R script from a job that does not belong to this user!');
            return Response::json($response, 401);
        }

        $r = file($fullpath);
        return Response::json($r, 200);
    }

    /**
     * Retrieves a file from a job's folder.
     *
     * @param int $job_id
     * @param string $filename
     * @return View|file|JSON
     */
    public function get_job_file($job_id, $filename)
    {
        $userInfo = session('user_info');
        $user_email = $userInfo['email'];
        $job_folder = $this->jobs_path . '/' . $user_email . '/job' . $job_id;
        $fullpath = $job_folder . '/' . $filename;

        if (!file_exists($fullpath)) {
            $errorMessage = "Trying to retrieve non existent file.";
            return $this->illegalActionResponse($errorMessage, 400);
        }

        // Check if this job belongs to this user
        $result = DB::table('jobs')
                ->where('id', $job_id)
                ->where('user_email', $user_email)
                ->first();

        if (!empty($result)) {
            $parts = pathinfo($filename);
            $new_filename = $parts['filename'] . '_job' . $job_id . '.' . $parts['extension'];

            switch ($parts['extension']) {
                case 'png':
                    return response()->download($fullpath, $new_filename, ['Content-Type' => 'image/png']);
                    break;
                case 'csv':
                    return response()->download($fullpath, $new_filename, ['Content-Type' => 'text/plain', 'Content-Disposition' => 'attachment; filename=' . $new_filename]);
                    break;
                case 'nwk':
                case 'pdf':
                    return response()->download($fullpath, $new_filename, ['Content-Type' => 'application/octet-stream']);
                    break;
            }
        } else {
            $this->log_event("Trying to retrieve a file that does not belong to a user's job.", "unauthorized");
            if ($this->is_mobile) {
                $response = array('message', "Trying to retrieve a file that does not belong to a user's job");
                return Response::json($response, 401);
            } else {
                abort(403, 'Unauthorized action.');
            }
        }
    }

    /**
     * Submits a new job
     *
     * Handles the basic functionlity of submission that is not related to a specific R vLab function
     *
     * @param Request $request
     * @return type
     */
    public function submit(Request $request)
    {

        $user_email = session('user_info.email');
        $form = $request->all();

        $this->jobHelper->basicFormValidation($form);

        try {
            $job = $this->createBasicJobRecord($user_email, $form);

            // Get the job id and create the job folder
            $job_id = 'job' . $job->id;

            // Define all the required paths
            $user_jobs_path = $this->jobs_path . '/' . $user_email;
            $job_folder = $user_jobs_path . '/' . $job_id;
            $user_workspace = $this->workspace_path . '/' . $user_email;
            $remote_job_folder = $this->remote_jobs_path . '/' . $user_email . '/' . $job_id;
            $remote_user_workspace = $this->remote_workspace_path . '/' . $user_email;

            $this->jobHelper->buildJobDirectories($job_id, $user_email);

            // Run the function
            $params = "";
            $inputs = "";

            $class = "\App\RAnalysis" . "\\" . strtolower($form['function']);
            $analysis = new $class($form, $job_id, $job_folder, $remote_job_folder, $user_workspace, $remote_user_workspace, $inputs, $params);
            $submitted = $analysis->run();
            //$submitted = $this->{$low_function}($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,$inputs,$params);
            // Handle submission failure
            if (!$submitted) {
                return $this->responseAfterFailure('Function ' . $form['function'] . ' failed!', 'Job submission failed. ', $job_id, $job_folder);
            }

            $this->updateJobAfterSubmission($job, $job_folder, $inputs, $params);
        } catch (Exception $ex) {
            $this->errorMessage = 'Job submission failed! ' . $this->errorMessage;

            if ($job_id) {
                return $this->responseAfterFailure($ex->getMessage(), $this->errorMessage, $job_id, $job_folder);
            } else {
                return $this->responseAfterFailure($ex->getMessage(), $this->errorMessage);
            }
        }

        Session::put('last_function_used', $form['function']);

        if ($this->is_mobile) {
            return Response::json(array(), 200);
        } else {
            Session::flash('toastr', array('success', 'The job submitted successfully!'));
            return Redirect::to('/');
        }
    }

    /**
     * Updates the job record after a successful job submission
     *
     * @param Job $job
     * @param string $job_folder
     * @param string $inputs
     * @param string $params
     */
    protected function updateJobAfterSubmission(Job &$job, $job_folder, $inputs, $params)
    {
        $job->status = 'submitted';
        $job->jobsize = directory_size($job_folder);
        $job->inputs = $this->jobHelper->addIdsToInputFiles($inputs);
        $job->parameters = trim($params, ";");
        $job->save();
    }

    /**
     * Handles a failed job submission
     *
     * @param string $logMessage
     * @param string $flashMessage
     * @param int $jogId
     * @param string $job_folder
     * @return mixed
     */
    protected function responseAfterFailure($logMessage, $flashMessage, $jogId = null, $job_folder = null)
    {
        // Delete the job directory, if created
        if ($jogId) {
            $this->jobHelper->deleteJobDirectory($jogId, $job_folder);
        }

        // Check if there is something to log
        if (!empty($logMessage)) {
            $this->log_event($logMessage, "error");
        }

        if ($this->is_mobile) {
            return Response::json(['message', $flashMessage], 500);
        } else {
            if (Session::has('toastr')) {
                $oldMessage = session('toastr');
                $flashMessage .= $oldMessage[1];
            }
            Session::flash('toastr', ['error', $flashMessage]);
            return Redirect::back();
        }
    }

    /**
     * Creates a job record with basic information (the job has not
     * been submitted yet)
     *
     * @param string $user_email
     * @param array $form
     * @return Job
     */
    protected function createBasicJobRecord($user_email, array $form)
    {
        $job = new Job();
        $job->user_email = $user_email;
        $job->function = $form['function'];
        $job->status = 'creating';
        $job->submitted_at = date("Y-m-d H:i:s");
        $job->save();

        return $job;
    }
}
