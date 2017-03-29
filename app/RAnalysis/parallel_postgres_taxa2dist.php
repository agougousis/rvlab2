<?php

namespace App\RAnalysis;

use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes a parallel_postgres_taxa2dist analysis
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
class parallel_postgres_taxa2dist extends BaseAnalysis implements RAnalysis
{
    /**
     * The input file to be used for the analysis
     *
     * @var string
     */
    private $box;

    /**
     * The check_parallel_taxa2dist parameter
     *
     * @var string
     */
    private $check_parallel_taxa2dist;

    /**
     * The varstep parameter
     *
     * @var string
     */
    private $varstep;

    /**
     * The no_of_processors parameter
     *
     * @var int
     */
    private $no_of_processors;

    /**
     * Initializes class properties
     */
    protected function init() {
        $this->formValidationRules = [
            'box'               => 'required|string|max:250',
            'varstep'           =>  'required|string|in:TRUE,FALSE',
            'check_parallel_taxa2dist'   =>  'required|string|in:TRUE,FALSE',
            'No_of_processors'  =>  'required|int'
        ];
    }

    /**
     * Runs a parallel_postgres_taxa2dist analysis
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

        $this->varstep = $this->form['varstep'];
        $this->params .= ";varstep:" . $this->varstep;

        $this->check_parallel_taxa2dist = $this->form['check_parallel_taxa2dist'];
        $this->params .= ";check_parallel_taxa2dist:" . $this->check_parallel_taxa2dist;

        $this->no_of_processors = $this->form['No_of_processors'];
        $this->params .= ";No_of_processors:".$this->no_of_processors;
    }

    /**
     * Builds the required executables for the job execution
     *
     * @throws Exception
     */
    protected function buildRScript()
    {
        // Build the R script
        $script_source = app_path().'/rvlab/files/taxa2distPostgresMPI.r';
        copy($script_source,"$this->job_folder/".$this->job_id.".R");

        // Build the bash script
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
        fwrite($fh2, "#PBS -l nodes=1:ppn=$this->no_of_processors\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $this->job_id.R $this->remote_job_folder/$this->box 1000000 $this->remote_job_folder/ $this->remote_job_folder/ $this->job_id $this->varstep $this->check_parallel_taxa2dist  > $this->remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);
    }
}
