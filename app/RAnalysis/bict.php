<?php

namespace App\RAnalysis;

use Session;
use Validator;
use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes a bict analysis
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
class bict extends BaseAnalysis implements RAnalysis
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
     * The species_family_select parameter
     *
     * @var string
     */
    private $species_family_select;

    /**
     * The validation rules for bict submission form
     *
     * @var array
     */
    private $formValidationRules = [
        'box' => 'required|string|max:250',
        'box2' => 'string|max:250',
        'species_family_select' => 'required|string|in:species,family'
    ];

    /**
     * Runs a bict analysis
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
        system("chmod +x $this->job_folder/indices");
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

        if ($this->box2) {
            $workspace_filepath = $this->user_workspace . '/' . $this->box2;
            $job_filepath = $this->job_folder . '/' . $this->box2;

            if (!copy($workspace_filepath, $job_filepath)) {
                throw new Exception('Moving file from workspace to job folder, failed.');
            }
        }

        $script_source = app_path() . '/rvlab/files/indices';
        if (!copy($script_source, "$job_folder/indices")) {
            throw new Exception('Moving file from workspace to job folder, failed.');
        }

        $bqi = app_path() . '/rvlab/files/bqi.csv';
        if (!copy($bqi, "$job_folder/bqi.csv")) {
            throw new Exception('Moving file from workspace to job folder, failed.');
        }

        $ambi = app_path() . '/rvlab/files/ambi.csv';
        if (!copy($ambi, "$job_folder/ambi.csv")) {
            throw new Exception('Moving file from workspace to job folder, failed.');
        }

        $bentix = app_path() . '/rvlab/files/bentix.csv';
        if (!copy($bentix, "$job_folder/bentix.csv")) {
            throw new Exception('Moving file from workspace to job folder, failed.');
        }

        $bqif = app_path() . '/rvlab/files/bqi.family.csv';
        if (!copy($bqif, "$job_folder/bqi.family.csv")) {
            throw new Exception('Moving file from workspace to job folder, failed.');
        }

        $distinct = app_path() . '/rvlab/files/TaxDistinctness.R';
        if (!copy($distinct, "$job_folder/TaxDistinctness.R")) {
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

        if (!empty($this->form['box2'])) {
            $this->box2 = $this->form['box2'];
            $this->inputs .= ";" . $this->box2;
        }

        $this->species_family_select = $this->form['species_family_select'];
        $this->params .= ";species_family_select:" . $this->species_family_select;
    }

    /**
     * Builds the required executables for the job execution
     *
     * @throws Exception
     */
    private function buildRScript()
    {
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
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");

        if ($sp_fam == 'species') {
            if (empty($this->box2)) {
                fwrite($fh2, "tr '\r' '\n' < $this->remote_job_folder/$this->box >$this->remote_job_folder/tmp.csv\n");
                fwrite($fh2, "$this->remote_job_folder/indices -$this->remote_job_folder/tmp.csv -$this->remote_job_folder/indices.txt -B/dev/null -X/dev/null -A/dev/null > $this->remote_job_folder/cmd_line_output.txt \n");
            } else {
                fwrite($fh2, "tr '\r' '\n' < $this->remote_job_folder/$this->box >$this->remote_job_folder/tmp.csv\n");
                fwrite($fh2, "tr '\r' '\n' < $this->remote_job_folder/$this->box2 > $this->remote_job_folder/tmp2.csv\n");
                fwrite($fh2, "$this->remote_job_folder/indices -$this->remote_job_folder/tmp.csv -$this->remote_job_folder/tmp2.csv -$this->remote_job_folder/indices.txt -B/dev/null -X/dev/null -A/dev/null > $this->remote_job_folder/cmd_line_output.txt\n");
            }
        } else {
            fwrite($fh2, "tr '\r' '\n' < $this->remote_job_folder/$this->box >$this->remote_job_folder/tmp.csv\n");
            fwrite($fh2, "$this->remote_job_folder/indices -$this->remote_job_folder/tmp.csv -f -$this->remote_job_folder/indices.txt -F/dev/null > $this->remote_job_folder/cmd_line_output.txt\n");
        }
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
    }
}
