<?php

namespace App\RAnalysis;

use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes a parallel_anosim analysis
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
class parallel_anosim extends BaseAnalysis implements RAnalysis
{
    /**
     * The input file to be used for the analysis
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
     * The transpose parameter
     *
     * @var string
     */
    private $transpose;

    /**
     * The permutations parameter
     *
     * @var string
     */
    private $permutations;

    /**
     * The column_select parameter
     *
     * @var string
     */
    private $column_select;

    /**
     * The method_select parameter
     *
     * @var string
     */
    private $method_select;

    /**
     * The no_of_processors parameter
     *
     * @var int
     */
    private $no_of_processors;

    /**
     * Initializes class properties
     */
    protected function init()
    {
        $this->formValidationRules = [
            'box' => 'required|string|max:250',
            'box2' => 'required|string|max:250',
            'transpose' => 'string|max:250',
            'permutations' => 'required|int',
            'column_select' => 'required|string|max:250',
            'method_select' => 'required|string|max:250',
            'No_of_processors' => 'required|int',
        ];
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

        if (empty($this->form['transpose'])) {
            $this->transpose = "FALSE";
            $this->params .= ";transpose: ";
        } else {
            $this->transpose = $this->form['transpose'];
            $this->params .= ";transpose:" . $this->transpose;
        }

        $this->method_select = $this->form['method_select'];
        $this->params .= ";method_select:" . $this->method_select;

        $this->permutations = $this->form['permutations'];
        $this->params .= ";permutations:".$this->permutations;

        $this->no_of_processors = $this->form['No_of_processors'];
        $this->params .= ";No_of_processors:".$this->no_of_processors;

        $this->column_select = $this->form['column_select'];
        $this->params .= ";column_select:".$this->column_select;
    }

    /**
     * Builds the required R script for the job execution
     *
     * @throws Exception
     */
    protected function buildRScript()
    {
        $script_source = app_path().'/rvlab/files/anosimMPI_24_09_2015.r';
        copy($script_source, "$this->job_folder/".$this->job_id.".R");
    }

    /**
     * Builds the required bash script for the job execution
     *
     * @throws Exception
     */
    protected function buildBashScript()
    {
        if (!($fh2 = fopen($this->job_folder . "/$this->job_id.pbs", "w"))) {
            throw new \Exception("Unable to open file $this->job_folder/$this->job_id.pbs");
        }

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $this->job_id\n");
        fwrite($fh2, "#PBS -d $this->remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $this->job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=".$this->no_of_processors."\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $this->remote_job_folder/$this->job_id.R $this->remote_job_folder/$this->box $this->transpose $this->remote_job_folder/$this->box2 $this->column_select $this->remote_job_folder/ $this->permutations $this->method_select > $this->remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);
    }
}
