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
use App\ClassHelpers\ConditionsChecker;
use App\ClassHelpers\AuthorizationChecker;
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
    /**
     * A helper object that handles subtasks related to R vLab job management
     *
     * @var JobHelper
     */
    protected $jobHelper;

    /**
     * A helper object that is used to check for necessery conditions
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
     * Displays the Home Page
     *
     * @return View
     */
    public function index()
    {
        $userInfo = session('user_info');

        $job_list = DB::table('jobs')->where('user_email', $userInfo['email'])->orderBy('id', 'desc')->get();

        $form_data = $this->loadFormData($userInfo['email']);

        if ($this->is_mobile) {
            $mobile_functions = config('mobile_functions.list');
            $response = array(
                'r_functions' => $mobile_functions,
                'job_list' => $job_list,
                'workspace_files' => $form_data['workspace_files'],
            );
            return Response::json($response, 200);
        } else {
            $data = $this->loadIndexViewData($form_data, $job_list, $userInfo);

            return $this->loadView('index', 'R vLab Home Page', $data);
        }
    }

    /**
     * Load data requird to build the job submission forms
     *
     * @param string $user_email
     * @return array
     */
    protected function loadFormData($user_email)
    {
        $form_data['workspace_files'] = WorkspaceFile::getUserFiles($user_email);
        $form_data['user_email'] = $user_email;
        $form_data['tooltips'] = config('tooltips');

        return $form_data;
    }

    /**
     * Load data required for the index page view
     *
     * @param array $form_data
     * @param array $job_list
     * @param array $userInfo
     * @return array
     */
    protected function loadIndexViewData($form_data, $job_list, $userInfo)
    {
        // Load configuration data
        $r_functions = config('r_functions.list');
        $data['r_functions'] = $r_functions;
        $data['refresh_rate'] = $this->system_settings['status_refresh_rate_page'];

        // Load subviews
        $forms = array();
        foreach ($r_functions as $codename => $title) {
            $forms[$codename] = View::make('forms.' . $codename, $form_data);
        }
        $data['forms'] = $forms;

        $data2['workspace_files'] = $form_data['workspace_files'];
        $data['workspace'] = View::make('workspace.manage', $data2);

        // Load session data
        $data['is_admin'] = session('is_admin');
        $data['deletion_info'] = Session::get('deletion_info', null);
        $data['workspace_tab_status'] = Session::get('workspace_tab_status', 'closed');
        $data['last_function_used'] = Session::get('last_function_used', 'taxa2dist');
        $data['timezone'] = $userInfo['timezone'];

        // Load database data
        $data['job_list'] = $job_list;
        $data['count_workspace_files'] = $form_data['workspace_files']->count();

        return $data;
    }

    /**
     *
     * @return \RedirectResponseDeletes selected jobs
     *
     * @return RedirectResponse
     */
    public function deleteManyJobs(Request $request)
    {
        $form = $request->all();

        // INPUT FILTERING: Basic submitted data validation
        if (empty($form['jobs_for_deletion'])) {
            return Redirect::to('/');
        }

        $job_list_string = $form['jobs_for_deletion'];
        $job_list = explode(';', $job_list_string);

        // CONDITION: Check the existance and integrity of all jobs related
        // to provided job IDs.
        $job_records = $this->conditionChecker->jobsAreDeletable($job_list);

        // ACCESS CONTROL: Jobs related to provided job IDs should be owned by
        // the logged in user
        AuthorizationChecker::jobsBelongToUser($job_records, session('user_info.email', 'delete_many_jobs'));

        // Delete the jobs
        $deletion_info = $this->jobHelper->deleteJobs($job_records);

        if ($this->is_mobile) {
            return Response::json($deletion_info, 200);
        } else {
            return Redirect::to('/')->with('deletion_info', $deletion_info);
        }
    }

    /**
     * Retrieves a file from a job's folder.
     *
     * @param int $job_id
     * @param string $filename
     * @return View|file|JSON
     */
    public function getJobFile($job_id, $filename)
    {
        $clean_filename = safe_filename(basename($filename));

        $user_email = session('user_info.email');

        // INPUT FILTERING: Check form data
        $validator = Validator::make(['job_id' => $job_id, 'filename' => $clean_filename], [
            'job_id'    => 'required|int',
            'filename'  => 'required|string|max:200'
        ]);

        if ($validator->fails()) {
            return $this->illegalActionResponse('Invalid file name or job ID!', 400);
        }

        // CONDITION: Job file exists
        $fullpath = $this->conditionChecker->jobFileExists($user_email, $job_id, $clean_filename);

        // ACCESS CONTROL: The job, which the requested file is part of, belongs
        // to the logged in user (and so, it exists!)
        AuthorizationChecker::jobBelongsToUser($job_id, $user_email);

        return $this->jobHelper->downloadJobFile($fullpath, $job_id);
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

        $job = $this->createBasicJobRecord($user_email, $form);

        // Get the job id and create the job folder
        $job_id = 'job' . $job->id;

        // Define all the required paths
        $user_jobs_path = $this->jobs_path . '/' . $user_email;
        $job_folder = $user_jobs_path . '/' . $job_id;

        try {
            $this->jobHelper->buildJobDirectories($job_id, $user_email);

            // Run the function
            $params = "";
            $inputs = "";

            $class = "\App\RAnalysis" . "\\" . strtolower($form['function']);
            $analysis = new $class($form, $job_id, $user_email, $inputs, $params);
            $analysis->run();

            $this->updateJobAfterSubmission($job, $job_folder, $inputs, $params);
        } catch (\Exception $ex) {
            $this->jobHelper->deleteJob($job);

            return $this->responseAfterFailure($ex->getMessage(), 'Job submission failed!', $job);
        }

        Session::put('last_function_used', $form['function']);

        return $this->okResponse('The job submitted successfully!');
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
     * @param Job $job
     * @return mixed
     */
    protected function responseAfterFailure($logMessage, $flashMessage, $job)
    {
        // Delete the job directory, if created
        $this->jobHelper->deleteJob($job);

        // Log the error
        $this->logEvent($logMessage, "error");

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
