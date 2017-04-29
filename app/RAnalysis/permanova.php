<?php

namespace App\RAnalysis;

use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes a permanova analysis
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
class permanova extends BaseAnalysis implements RAnalysis
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
     * The transf_method_select parameter
     *
     * @var string
     */
    private $transf_method_select;

    /**
     * The column_select parameter
     *
     * @var string
     */
    private $column_select;

    /**
     * The column_select2 parameter
     *
     * @var string
     */
    private $column_select2;

    /**
     * The permutations parameter
     *
     * @var string
     */
    private $permutations;

    /**
     * The method_select parameter
     *
     * @var string
     */
    private $method_select;

    /**
     * The single_or_multi parameter
     *
     * @var string
     */
    private $single_or_multi;

    /**
     * Initializes class properties
     */
    protected function init()
    {
        $this->formValidationRules = [
            'box'               => 'required|string|max:250',
            'box2'              => 'required|string|max:250',
            'transpose'         => 'string|max:250',
            'transf_method_select'  =>  'required|string|max:250',
            'column_select'     => 'required|string|max:250',
            'column_select2'    => 'required|string|max:250',
            'permutations'      => 'required|int',
            'method_select'     =>  'required|string|max:250',
            'single_or_multi'   =>  'required|string|max:250'
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
            $this->transpose = "";
            $this->params .= ";transpose: ";
        } else {
            $this->transpose = $this->form['transpose'];
            $this->params .= ";transpose:" . $this->transpose;
        }

        $this->transf_method_select=$this->form['transf_method_select'];
        $this->params .= ";transf_method_select:".$this->transf_method_select;

        $this->column_select=$this->form['column_select'];
        $this->params .= ";column_select:".$this->column_select;

        $this->column_select2=$this->form['column_select2'];
        $this->params .= ";column_select2:".$this->column_select2;

        $this->permutations=$this->form['permutations'];
        $this->params .= ";permutations:".$this->permutations;

        $this->method_select=$this->form['method_select'];
        $this->params .= ";method_select:".$this->method_select;

        $this->single_or_multi=$this->form['single_or_multi'];
        $this->params .= ";single_or_multi:".$this->single_or_multi;
    }

    /**
     * Builds the required executables for the job execution
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
        if ($this->transf_method_select != "none") {
            fwrite($fh, "mat <- decostand(mat, method = \"$this->transf_method_select\");\n");
        }
        if ($this->single_or_multi =="single") {
            fwrite($fh, "otu.ENVFACT.adonis <- adonis(mat ~ ENV\$$this->column_select,data=ENV,permutations = $this->permutations,distance = \"$this->method_select\");\n");
        } else {
            fwrite($fh, "otu.ENVFACT.adonis <- adonis(mat ~ ENV\$$this->column_select+ENV\$$this->column_select2,data=ENV,permutations = $this->permutations,distance = \"$this->method_select\");\n");
        }

        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "otu.ENVFACT.adonis\n");
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
