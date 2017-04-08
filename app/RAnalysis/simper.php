<?php

namespace App\RAnalysis;

use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes a simper analysis
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
 */
class simper extends BaseAnalysis implements RAnalysis
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
     * The transpose parameter
     *
     * @var string
     */
    private $transpose;

    /**
     * The column_select parameter
     *
     * @var string
     */
    private $column_select;

    /**
     * The trace parameter
     *
     * @var string
     */
    private $trace;

    /**
     * The permutations parameter
     *
     * @var string
     */
    private $permutations;

    /**
     * Initializes class properties
     */
    protected function init()
    {
        $this->formValidationRules = [
            'box' => 'required|string|max:250',
            'box2' => 'required|string|max:250',
            'transpose' => 'string|max:250',
            'column_select' => 'required',
            'trace' => 'required|string|in:TRUE,FALSE',
            'permutations' => 'required|int'
        ];
    }

    /**
     * Runs a simper analysis
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
            $this->transpose = "";
            $this->params .= ";transpose: ";
        } else {
            $this->transpose = $this->form['transpose'];
            $this->params .= ";transpose:" . $this->transpose;
        }

        $this->column_select = $this->form['column_select'];
        $this->params .= ";column_select:" . $this->column_select;

        $this->trace = $this->form['trace'];
        $this->params .= ";trace:" . $this->trace;

        $this->permutations = $this->form['permutations'];
        $this->params .= ";permutations:" . $this->permutations;
    }

    /**
     * Builds the required R script for the job execution
     *
     * @throws Exception
     */
    protected function buildRScript()
    {
        if (!($fh = fopen("$this->job_folder/$this->job_id.R", "w"))) {
            throw new \Exception("Unable to open file $this->job_folder/$this->job_id.R");
        }

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "ENV <- read.table(\"$this->remote_job_folder/$this->box2\",header = TRUE, sep=\",\",row.names=1);\n");
        fwrite($fh, "mat <- read.table(\"$this->remote_job_folder/$this->box\", header = TRUE, sep=\",\" ,row.names=1);\n");

        if ($this->transpose == "transpose") {
            fwrite($fh, "mat <- t(mat);\n");
        }

        fwrite($fh, "otu.ENVFACT.simper <- simper(mat,ENV\$$this->column_select,permutations = $this->permutations,trace = $this->trace);\n");
        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "otu.ENVFACT.simper\n");
        fclose($fh);
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
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $this->remote_job_folder/$this->job_id.R > $this->remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);
    }
}
