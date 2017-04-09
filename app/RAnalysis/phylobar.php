<?php

namespace App\RAnalysis;

use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes a phylobar analysis
 *
 * BaseAnalysis initializes the following properties:
 *   $form
 *   $job_id
 *   $job_folder
 *   $remote_job_folder
 *   $user_workspace
 *   $remote_user_workspace
 *   &$inputs
 *   &$params
 *
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 * @author Anastasios Oulas <oulas@hcmr.gr>
 */
class phylobar extends BaseAnalysis implements RAnalysis
{
    /**
     * The first input file to be used for the analysis
     *
     * @var string
     */
    private $box;

    /**
     * The second input file to be used for the analysis
     *
     * @var string
     */
    private $box2;

    /**
     * The top_nodes parameter
     *
     * @var string
     */
    private $top_nodes;

    /**
     * Initializes class properties
     */
    protected function init()
    {
        $this->formValidationRules = [
            'box' => 'required|string|max:250',
            'box2' => 'required|string|max:250',
            'top_nodes' => 'required|int'
        ];
    }

    /**
     * Runs a phylobar analysis
     */
    public function run()
    {
        $this->validateForm();

        $this->getInputParams();

        $this->copyInputFiles();

        $this->makeFakeStatus();

        // Execute the bash script
        system("chmod +x $this->job_folder/$this->job_id.pbs");
        system("$this->job_folder/$this->job_id.pbs > /dev/null 2>&1 &");
    }

    /**
     * Moved input files from workspace to job's folder
     *
     * @throws Exception
     */
    protected function copyInputFiles()
    {
        $workspace_filepath = $this->user_workspace . '/' . $this->box;
        $job_filepath = $this->job_folder . '/' . $this->box;

        if (!copy($workspace_filepath, $job_filepath)) {
            throw new Exception('Moving file from workspace to job folder, failed.');
        }

        $workspace_filepath = $this->user_workspace . '/' . $this->box2;
        $job_filepath = $this->job_folder . '/' . $this->box2;

        if (!copy($workspace_filepath, $job_filepath)) {
            throw new Exception('Moving file from workspace to job folder, failed.');
        }
    }

    /**
     * Retrieves input parameters from form data
     *
     * @throws Exception
     */
    protected function getInputParams()
    {
        $this->box = $this->form['box'];

        $this->box2 = $this->form['box2'];
        $this->inputs .= ";" . $this->box2;

        $this->top_nodes = $this->form['top_nodes'];
        $this->params .= ";top_nodes:" . $this->top_nodes;
    }

    /**
     * This analysis has no executables. This is a javascript-based analysis.
     * But a fake job#.jobstatus file is needed to mark its status as
     * completed and a dummy job#.submitted is needed to mark it as submitted.
     *
     * @throws Exception
     */
    protected function makeFakeStatus()
    {
        // There are no executables. This is a javascript-based analysis.
        // But a fake job#.jobstatus file is needed to mark its status as
        // completed and a dummy job#.submitted is needed to mark it as submitted.
        file_put_contents($this->job_folder.'/'.$this->job_id.'.submitted', 'dummy text');

        $now = (new \DateTime())->format('Y-m-d H:i:s');
        $userInfo = session('user_info');
        $userEmail = $userInfo['email'];
        file_put_contents($this->job_folder.'/'.$this->job_id.'.jobstatus', "$userEmail job$this->job_id 00000 $now $now NA ended NA");
    }
}
