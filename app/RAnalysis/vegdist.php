<?php

namespace App\RAnalysis;

use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes a vegdist analysis
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
class vegdist extends BaseAnalysis implements RAnalysis
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
     * The transf_method_select parameter
     *
     * @var string
     */
    private $transf_method_select;

    /**
     * The index parameter
     *
     * @var string
     */
    private $index;

    /**
     * The upto parameter
     *
     * @var string
     */
    private $upto;

    /**
     * The trace parameter
     *
     * @var string
     */
    private $trace;

    /**
     * Initializes class properties
     */
    protected function init() {
        $this->formValidationRules = [
            'box' => 'required|string|max:250',
            'box2' => 'string|max:250',
            'transpose' =>  '',
            'transf_method_select' => '',
            'index' => '',
            'upto' => '',
            'trace' => ''
        ];
    }

    /**
     * Runs a vegdist analysis
     *
     * @return boolean
     */
    public function run()
    {
        try {
            $this->validateForm();

            $this->getInputParams();

            $this->copyInputFiles();

            $this->buildRScript();
        } catch (\Exception $ex) {
            if (!empty($ex->getMessage())) {
                $this->log_event($ex->getMessage(), "error");
            }

            return false;
        }

        // Execute the bash script
        system("chmod +x $this->job_folder/$this->job_id.pbs");
        system("$this->job_folder/$this->job_id.pbs > /dev/null 2>&1 &");

        return true;
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
    }

    /**
     * Retrieves input parameters from form data
     *
     * @throws Exception
     */
    protected function getInputParams()
    {
        $this->box = $this->form['box'];

        if (empty($this->form['transpose'])) {
            $this->transpose = "";
            $this->params .= ";transpose: ";
        } else {
            $this->transpose = $this->form['transpose'];
            $this->params .= ";transpose:" . $this->transpose;
        }

        $this->transf_method_select = $this->form['transf_method_select'];
        $this->params .= ";transf_method_select:" . $this->transf_method_select;

        $this->method_select = $this->form['method_select'];
        $this->params .= ";method:" . $this->method_select;

        $this->bin = $this->form['binary_select'];
        $this->params .= ";binary:" . $this->bin;

        $this->diag = $this->form['diag_select'];
        $this->params .= ";diag:" . $this->diag;

        $this->upper = $this->form['upper_select'];
        $this->params .= ";upper:" . $this->upper;

        $this->na = $this->form['na_select'];
        $this->params .= ";na.rm:" . $this->na;
    }

    /**
     * Builds the required executables for the job execution
     *
     * @throws Exception
     */
    protected function buildRScript()
    {
        // Build the R script
        if (!($fh = fopen("$this->job_folder/$this->job_id.R", "w"))) {
            throw new \Exception("Unable to open file $this->job_folder/$this->job_id.R");
        }

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "mat <- read.table(\"$this->remote_job_folder/$this->box\", header = TRUE, sep=\",\",row.names=1);\n");

        if ($this->transpose == "transpose") {
            fwrite($fh, "mat <- t(mat);\n");
        }

        if ($this->transf_method_select != "none") {
            fwrite($fh, "mat <- decostand(mat, method = \"$this->transf_method_select\");\n");
        }

        fwrite($fh, "vegdist <- vegdist(mat, method = \"$this->method_select\",binary=$this->bin, diag=$this->diag, upper=$this->upper,na.rm = $this->na)\n");
        fwrite($fh, "save(vegdist, ascii=TRUE, file = \"$this->remote_job_folder/vegdist.csv\");\n");
        fwrite($fh, "summary(vegdist);\n");
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($this->job_folder . "/$this->job_id.pbs", "w"))) {
            throw new \Exception("Unable to open file: $this->job_folder/$this->job_id.pbs");
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