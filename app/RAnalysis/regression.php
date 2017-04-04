<?php

namespace App\RAnalysis;

use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes a regression analysis
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
class regression extends BaseAnalysis implements RAnalysis
{
    /**
     * The first input file to be used for the analysis
     *
     * @var string
     */
    private $box;

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
     * The single_or_multi parameter
     *
     * @var string
     */
    private $single_or_multi;

    /**
     * The Factor_select1 parameter
     *
     * @var string
     */
    private $factor_select1;

    /**
     * The Factor_select2 parameter
     *
     * @var string
     */
    private $factor_select2;

    /**
     * The Factor_select3 parameter
     *
     * @var string
     */
    private $factor_select3;

    /**
     * Initializes class properties
     */
    protected function init()
    {
        $this->formValidationRules = [
            'box'       =>  'required|string|max:250',
            'transpose' => 'string|max:250',
            'transf_method_select'  =>  'required|string|max:250',
            'single_or_multi'   =>  'required|string|max:250',
            'Factor_select1'    => 'required|string|max:250',
            'Factor_select2'    => 'required|string|max:250',
            'Factor_select3'    =>  'string|max:250'
        ];
    }

    /**
     * Runs a regression analysis
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

            $this->buildBashScript();
        } catch (\Exception $ex) {
            if (!empty($ex->getMessage())) {
                $this->logEvent($ex->getMessage(), "error");
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

        $this->single_or_multi = $this->form['single_or_multi'];
        $this->params .= ";single_or_multi:" . $this->single_or_multi;

        $this->factor_select1 = $this->form['Factor_select1'];
        $this->params .= ";Factor_select1:" . $this->factor_select1;

        $this->factor_select2 = $this->form['Factor_select2'];
        $this->params .= ";Factor_select2:" . $this->factor_select2;

        $this->factor_select3 = $this->form['Factor_select3'];
        if (empty($this->factor_select3)) {
            $this->params .= ";Factor_select3: ";
        } else {
            $this->params .= ";Factor_select3:" . $this->factor_select3;
        }
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

        fwrite($fh, "library(stats);\n");
        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "fact <- read.table(\"$this->remote_job_folder/$this->box\", row.names=1, header = TRUE, sep=\",\");\n");

        if ($this->transf_method_select != "none") {
            fwrite($fh, "fact <- decostand(fact, method = \"$this->transf_method_select\");\n");
        }

        fwrite($fh, "attach(fact);\n");
        if ($this->single_or_multi =="single") {
            fwrite($fh, "fit<-lm($this->factor_select1~$this->factor_select2);\n");
            fwrite($fh, "png('rplot.png')\n");
            fwrite($fh, "plot($this->factor_select1~$this->factor_select2)\n");//, xlim = c(3, 5), ylim = c(4, 10))\n");
            fwrite($fh, "abline(fit, col=\"red\")\n");
            fwrite($fh, "dev.off()\n");

        } else {
            fwrite($fh, "fit<-lm($this->factor_select1~$this->factor_select2+$this->factor_select3);\n");
            fwrite($fh, "png('rplot.png')\n");
            fwrite($fh, "plot($this->factor_select1~$this->factor_select2+$this->factor_select3)\n");//, xlim = c(3, 5), ylim = c(4, 10))\n");
            fwrite($fh, "abline(fit, col=\"red\")\n");
            fwrite($fh, "dev.off()\n");
        }
        fwrite($fh, "png('rplot2.png')\n");
        fwrite($fh, "layout(matrix(c(1,2,3,4),2,2))\n");
        fwrite($fh, "plot(fit)\n");//, xlim = c(3, 5), ylim = c(4, 10))\n");
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "summary(fit);\n");
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
