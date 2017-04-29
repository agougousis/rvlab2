<?php

namespace App\RAnalysis;

use Session;
use Validator;
use App\Models\SystemLog;

/**
 * Description of BaseAnalysis
 *
 * @author Alexandros
 */
abstract class BaseAnalysis
{
    /**
     * The submitted form
     *
     * @var array
     */
    protected $form;

    /**
     * The job ID
     *
     * @var int
     */
    protected $job_id;

    /**
     * The local path to the job folder
     *
     * @var string
     */
    protected $job_folder;

    /**
     * The remote path (in cluster's filesystem) to the job folder
     *
     * @var string
     */
    protected $remote_job_folder;

    /**
     * The local path to the user's workspace
     *
     * @var string
     */
    protected $user_workspace;

    /**
     * The remote path (in cluster's filesystem) to the user's workspace
     *
     * @var string
     */
    protected $remote_user_workspace;

    /**
     * A list of all the selected filenames to be used as input
     * separated by semicolons
     *
     * @var string
     */
    protected $inputs;

    /**
     * A list of all the input parameters. Each parameters is expressed as a
     * key-value pair separated from tge others by a semicolon. Keys are
     * separated from values by a colon character.
     *
     * @var string
     */
    protected $params;

    /**
     * The validation rules for parallel_taxa2dist submission form
     *
     * @var array
     */
    protected $formValidationRules;

    public function __construct($form, $job_id, $user_email, &$inputs, &$params)
    {
        $workspace_path = config('rvlab.workspace_path');
        $jobs_path = config('rvlab.jobs_path');
        $remote_jobs_path = config('rvlab.remote_jobs_path');
        $remote_workspace_path = config('rvlab.remote_workspace_path');

        $user_jobs_path = $jobs_path . '/' . $user_email;

        $this->form = $form;
        $this->job_id = $job_id;
        $this->job_folder = $user_jobs_path . '/' . $job_id;
        $this->remote_job_folder = $remote_jobs_path . '/' . $user_email . '/' . $job_id;
        $this->user_workspace = $workspace_path . '/' . $user_email;
        $this->remote_user_workspace = $remote_workspace_path . '/' . $user_email;
        $this->params = &$params;
        $this->inputs = &$inputs;

        if (is_array($form['box'])) {
            $this->inputs = implode(';', $form['box']);
        } else {
            $this->inputs = $form['box'];
        }

        $this->init();
    }

    /**
     * Runs the designated analysis
     */
    public function run()
    {
        $this->validateForm();

        $this->getInputParams();

        $this->copyInputFiles();

        $this->buildRScript();

        $this->buildBashScript();

        // Execute the bash script
        system("chmod +x $this->job_folder/$this->job_id.pbs");
        system("$this->job_folder/$this->job_id.pbs > /dev/null 2>&1 &");
    }

    /**
     * Initializes class properties
     */
    abstract protected function init();

    /**
     * Moved input files from workspace to job's folder
     *
     * @throws Exception
     */
    abstract protected function copyInputFiles();

    /**
     * Retrieves input parameters from form data
     *
     * @throws Exception
     */
    abstract protected function getInputParams();

    /**
     * Builds the required R script for the job execution
     *
     */
    abstract protected function buildRScript();

    /**
     * Builds the required bash script for the job execution
     */
    abstract protected function buildBashScript();

    /**
     * Validates the submitted form
     *
     * @throws \Exception
     */
    protected function validateForm()
    {
        $validator = Validator::make($this->form, $this->formValidationRules);

        if ($validator->fails()) {
            // Load validation error messages to a session toastr
            $message = implode('<br>', $validator->errors()->all());
            Session::flash('toastr', ['error', $message]);
            throw new \Exception($message);
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
        $route = explode('@', \Route::currentRouteName());

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
