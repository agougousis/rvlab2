<?php

namespace App\RAnalysis;

use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes a convert2r analysis
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
class convert2r extends BaseAnalysis implements RAnalysis
{
    /**
     * The first input file to be used for the analysis
     *
     * @var string
     */
    private $box;

    /**
     * The species_family_select parameter
     *
     * @var string
     */
    private $species_family_select;

    /**
     * Initializes class properties
     */
    protected function init()
    {
        $this->formValidationRules = [
            'box'           =>  'required|string|max:250',
            'header1_id'    =>  'required|string|max:250',
            'header2_id'    =>  'required|string|max:250',
            'header3_id'    =>  'required|string|max:250',
            'header1_fact'  =>  'required|string|max:250',
            'header2_fact'  =>  'required|string|max:250',
            'header3_fact'  =>  'required|string|max:250',
            'function_to_run'   =>  'required|string|max:250'
        ];
    }

    /**
     * Runs a convert2r analysis
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

        $this->header1 = $this->form['header1_id'];
        $this->params .= ";header1_id:".$this->header1;

        $this->header2 = $this->form['header2_id'];
        $this->params .= ";header2_id:".$this->header2;

        $this->header3 = $this->form['header3_id'];
        $this->params .= ";header3_id:".$this->header3;

        $this->function_to_run = $this->form['function_to_run'];
        $this->params .= ";function_to_run:".$this->function_to_run;

        $this->header1_fact = $this->form['header1_fact'];
        $this->params .= ";header1_fact:".$this->header1_fact;

        $this->header2_fact = $this->form['header2_fact'];
        $this->params .= ";header2_fact:".$this->header2_fact;

        $this->header3_fact = $this->form['header3_fact'];
        $this->params .= ";header3_fact:".$this->header3_fact;
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

        fwrite($fh, "library(reshape);\n");
        fwrite($fh, "geo <- read.table(\"$this->remote_job_folder/$this->box\", header = TRUE, sep=\",\");\n");
        fwrite($fh, "write.table(geo, file = \"$this->remote_job_folder/transformed_dataAbu.csv\",sep=\",\",quote = FALSE,row.names = FALSE);\n");
        fwrite($fh, "geoabu<-cast(geo, $this->header1~$this->header2, $this->function_to_run, value=\"$this->header3\");\n");
        fwrite($fh, "write.table(geoabu, file = \"$this->remote_job_folder/transformed_dataAbu.csv\",sep=\",\",quote = FALSE,row.names = FALSE);\n");
        fwrite($fh, "geofact = data.frame(geo$$this->header1_fact,geo$$this->header2_fact,geo$$this->header3_fact);\n");
        fwrite($fh, "names(geofact) <- c(\"$this->header1_fact\",\"$this->header2_fact\",\"$this->header3_fact\");\n");
        fwrite($fh, "geofact <- subset(geofact, !duplicated(geofact$$this->header1_fact));\n");
        fwrite($fh, "rownames(geofact) <- NULL;\n");
        fwrite($fh, "write.table(geofact, file = \"$this->remote_job_folder/transformed_dataFact.csv\",sep=\",\",quote = FALSE,row.names = FALSE);\n");
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
