<?php

namespace App\RAnalysis;

use Session;
use Validator;
use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes a parallel_taxa2taxon analysis
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
class parallel_taxa2taxon extends BaseAnalysis implements RAnalysis
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
     * The validation rules for parallel_taxa2taxon submission form
     *
     * @var array
     */
    private $formValidationRules = [
        'box'               => 'required|string|max:250',
        'box2'               => 'required|string|max:250',
        'varstep'           =>  'required|string|in:TRUE,FALSE',
        'no_of_processors'  =>  'required|int'
    ];

    /**
     * Runs a parallel_taxa2taxon analysis
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
        } catch (Exception $ex) {
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
     * Validates the submitted form
     *
     * @throws \Exception
     */
    private function validateForm()
    {
        $validator = Validator::make($this->form, $this->formValidationRules);

        if ($validator->fails()) {
            // Load validation error messages to a session toastr
            Session::flash('toastr', implode('<br>', $validator->errors()->all()));
            throw new \Exception('');
        }
    }

    /**
     * Moved input files from workspace to job's folder
     *
     * @throws Exception
     */
    private function copyInputFiles()
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
    private function getInputParams()
    {
        $this->box = $this->form['box'];

        $this->box2 = $this->form['box2'];
        $this->inputs .= ";" . $this->box2;

        $this->varstep = $this->form['varstep'];
        $this->params .= ";varstep:" . $this->varstep;

        $this->no_of_processors = $this->form['No_of_processors'];
        $this->params .= ";No_of_processors:".$this->no_of_processors;
    }

    /**
     * Builds the required executables for the job execution
     *
     * @throws Exception
     */
    private function buildRScript()
    {
        // Build the R script
        $script_source = app_path().'/rvlab/files/taxa2dist_taxondive.r';
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
        fwrite($fh2, "#PBS -l nodes=1:ppn=".$this->no_of_processors."\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $this->remote_job_folder/$this->job_id.R $this->remote_job_folder/$this->box TRUE $this->remote_job_folder/$this->box2 TRUE 39728447488 $this->remote_job_folder/ $this->varstep > $this->remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);
    }
}
