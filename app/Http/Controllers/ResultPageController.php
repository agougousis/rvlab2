<?php

namespace App\Http\Controllers;

use Session;
use Response;
use App\Models\Job;
use App\Models\WorkspaceFile;
use App\ClassHelpers\RvlabParser;
use App\Http\Controllers\JobController;

/**
 * ....
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class ResultPageController extends JobController
{
    /**
     * Displays the results page of a job
     *
     * @param int $job_id
     * @return View|JSON
     */
    public function job_page($job_id)
    {
        $user_email = session('user_info.email');

        $data['job'] = $job = Job::where('user_email', $user_email)->where('id', $job_id)->first();

        // In case job id wasn't found
        if (empty($job)) {
            return $this->jobIdNotFoundResponse();
        }

        // Load information about input files
        $inputs = $this->getInputFilesList($job);

        // If job execution has not finished, try to update its status
        if (in_array($job->status, array('submitted', 'running', 'queued'))) {
            $this->jobHelper->refresh_job_status($job_id);
        }

        $data['function'] = $job->function;

        // If job has failed
        if ($job->status == 'failed') {
            $this->loadPotentialErrorMessages($job, $data);

            // Send back information about possible error messages/output
            if ($this->is_mobile) {
                $response = array('message', 'Error occured during submission.');
                return Response::json($response, 500);
            } else {
                return $this->load_view('results/failed', 'Job Results', $data);
            }
        }

        // If job is pending
        if (in_array($job->status, array('submitted', 'queued', 'running'))) {
            if ($this->is_mobile) {
                $response = array('data', $data);
                return Response::json($response, 500);
            } else {
                $data['refresh_rate'] = $this->system_settings['status_refresh_rate_page'];
                return $this->load_view('results/submitted', 'Job Results', $data);
            }
        }

        $job_folder = $this->jobs_path . '/' . $user_email . '/job' . $job_id;

        // Build the result page for this job
        return $this->build_result_page($job, $job_folder, $inputs);
    }

    /**
     * Creates an appropriate response for a job ID that does not exist
     *
     * @return mixed
     */
    private function jobIdNotFoundResponse()
    {
        if ($this->is_mobile) {
            $response = array('message', 'You have not submitted recently any job with such ID!');
            return Response::json($response, 400);
        } else {
            Session::flash('toastr', array('error', 'You have not submitted recently any job with such ID!'));
            return Redirect::back();
        }
    }

    /**
     * Search for error messages in a failed job
     *
     * @param Job $job
     * @param array $data
     */
    protected function loadPotentialErrorMessages(Job $job, array &$data)
    {
        $job_folder = $this->jobs_path . '/' . $job->user_email . '/job' . $job->id;
        $log_file = $job_folder . "/job" . $job->id . ".log";

        // Decide which file should be parsed for messages
        switch ($job->function) {
            case 'simper':
            case 'bict':
            case 'parallel_anosim':
            case 'parallel_taxa2dist':
            case 'parallel_postgres_taxa2dist':
            case 'parallel_mantel':
            case 'parallel_taxa2taxon':
            case 'parallel_permanova':
            case 'parallel_bioenv':
            case 'parallel_simper':
                $fileToParseForErrors = '/cmd_line_output.txt';
                break;
            default:
                $fileToParseForErrors = '/job' . $job->id . '.Rout';
        }

        // Parse the file
        $parser = new RvlabParser();
        $parser->parse_output($job_folder . $fileToParseForErrors);

        // We should also look for messages in the job's log file
        if ($parser->hasFailed()) {
            $data['errorString'] = implode("<br>", $parser->getOutput());
            $data['errorString'] .= $parser->parse_log($log_file);
        } else {
            $data['errorString'] = "Error occured during submission.";
            $data['errorString'] .= $parser->parse_log($log_file);
        }
    }

    /**
     * Returns a list of input filenames that were used for a submitted job
     *
     * @param Job $job
     * @return array
     */
    protected function getInputFilesList(Job $job)
    {
        $inputs = array();

        $input_files = explode(';', $job->inputs);

        foreach ($input_files as $ifile) {
            $info = explode(':', $ifile);
            $id = $info[0];
            $filename = $info[1];
            $record = WorkspaceFile::where('id', $id)->first();

            if (empty($record)) {
                $exists = false;
            } else {
                $exists = true;
            }

            $inputs[] = array(
                'id' => $id,
                'filename' => $filename,
                'exists' => $exists
            );
        }

        return $inputs;
    }

    /**
     * Builds the job results page
     *
     * @param Job $job
     * @param string $job_folder
     * @param array $input_files
     * @return mixed
     */
    private function build_result_page(Job $job, $job_folder, array $input_files)
    {
        $data = array();

        $data['function'] = $job->function;
        $data['job'] = $job;
        $data['input_files'] = $input_files;
        $data['job_folder'] = $job_folder;

        $this->loadMainResultFilename($job->function, $data);
        $this->loadResultImages($job->function, $data);

        if (!$this->loadResultHtml($job, $data)) {
            if ($this->is_mobile) {
                return array('data', $data);
            } else {
                return $this->load_view('results/failed', 'Job Results', $data);
            }
        }

        if ($this->is_mobile) {
            unset($data['content']);
            return Response::json(array('data', $data), 200);
        } else {
            return $this->load_view('results/completed', 'Job Results', $data);
        }
    }

    /**
     * Defines the main output file that was prodused by an analysis
     *
     * @param string $jobFunction
     * @param array $data
     */
    protected function loadMainResultFilename($jobFunction, array &$data)
    {
        switch ($jobFunction) {
            case 'taxa2dist':
                $data['dir_prefix'] = "taxadis";
                $data['blue_disk_extension'] = '.csv';
                break;
            case 'taxondive':
            case 'vegdist':
                $data['dir_prefix'] = $jobFunction;
                $data['blue_disk_extension'] = '.csv';
                break;
            case 'hclust':
            case 'metamds':
            case 'pca':
            case 'anosim':
            case 'anova':
            case 'radfit':
                $data['dir_prefix'] = "rplot";
                $data['blue_disk_extension'] = '.png';
                break;
            case 'heatcloud':
            case 'phylobar':
                $data['dir_prefix'] = "table";
                $data['blue_disk_extension'] = '.csv';
                break;
            case 'bict':
                $data['dir_prefix'] = "indices";
                $data['blue_disk_extension'] = '.txt';
                break;
            case 'second_metamds':
                $data['dir_prefix'] = "dist_2nd_stage";
                $data['blue_disk_extension'] = '.csv';
                break;
            case 'cca':
            case 'regression':
            case 'permanova':
            case 'mantel':
            case 'bioenv':
            case 'simper':
            case 'parallel_anosim':
            case 'parallel_bioenv':
            case 'parallel_simper':
            case 'parallel_mantel':
            case 'parallel_taxa2taxon':
            case 'parallel_permanova':
                $data['dir_prefix'] = "";
                $data['blue_disk_extension'] = '';
                break;
            case 'metamds_visual':
            case 'mapping_tools_visual':
            case 'mapping_tools_div_visual':
            case 'cca_visual':
                $data['dir_prefix'] = "filtered_abundance";
                $data['blue_disk_extension'] = '.csv';
                break;
            case 'convert2r':
                $data['dir_prefix'] = "transformed_dataAbu";
                $data['blue_disk_extension'] = '.csv';
                break;
            case 'parallel_taxa2dist':
            case 'parallel_postgres_taxa2dist':
                $data['dir_prefix'] = "RvLAB_taxa2Distoutput";
                $data['blue_disk_extension'] = '.csv';
                break;
        }
    }

    /**
     * Defines a list of images that were produced as output by an analysis
     *
     * @param string $jobFunction
     * @param array $data
     */
    protected function loadResultImages($jobFunction, array &$data)
    {
        switch ($jobFunction) {
            case 'taxa2dist':
            case 'vegdist':
            case 'bict':
            case 'mantel':
            case 'heatcloud':
            case 'permanova':
            case 'phylobar':
            case 'bioenv':
            case 'simper':
            case 'convert2r':
            case 'metamds_visual':
            case 'cca_visual':
            case 'mapping_tools_visual':
            case 'mapping_tools_div_visual':
            case 'parallel_anosim':
            case 'parallel_bioenv':
            case 'parallel_simper':
            case 'parallel_mantel':
            case 'parallel_permanova':
            case 'parallel_taxa2dist':
            case 'parallel_postgres_taxa2dist':
                $data['images'] = array();
                break;
            case 'taxondive':
            case 'hclust':
            case 'metamds':
            case 'second_metamds':
            case 'pca':
                $data['images'] = array('rplot.png', 'legend.png');
                break;
            case 'anosim':
            case 'cca':
            case 'anova':
            case 'radfit':
                $data['images'] = array('rplot.png');
                break;
            case 'regression':
                $data['images'] = array('rplot.png', 'rplot2.png');
                break;
            case 'parallel_taxa2taxon':
                $data['images'] = array('parallelTaxTaxOnPlot.png');
                break;
        }
    }

    /**
     * Extracts the text/html output from job results
     *
     * @param Job $job
     * @param array $data
     * @return boolean
     */
    protected function loadResultHtml(Job $job,array &$data)
    {
        switch ($job->function) {
            case 'taxa2dist':
            case 'taxondive':
            case 'vegdist':
            case 'hclust':
            case 'metamds':
            case 'second_metamds':
            case 'pca':
            case 'cca':
            case 'regression':
            case 'anosim':
            case 'anova':
            case 'permanova':
            case 'mantel':
            case 'radfit':
            case 'bioenv':
            case 'simper':
            case 'convert2r':
                $data['lines'] = $this->parseOutput('job' . $job->id . '.Rout', $data);
                if ($data['lines'] === false) {
                    return false;
                }
                break;
            case 'parallel_taxa2dist':
            case 'parallel_postgres_taxa2dist':
            case 'parallel_anosim':
            case 'parallel_bioenv':
            case 'parallel_simper':
            case 'parallel_mantel':
            case 'parallel_taxa2taxon':
            case 'parallel_permanova':
                $data['lines'] = $this->parseOutput('cmd_line_output.txt', $data);
                if ($data['lines'] === false) {
                    return false;
                }
                break;
            case 'bict':
                $data['lines'] = $this->parseBictOutput($data);
                if ($data['lines'] === false) {
                    return false;
                }
                break;
            case 'phylobar':
                $data['table_nwk'] = url("/storage/get_job_file/job/$job->id/table.nwk");
                $data['table_csv'] = url("/storage/get_job_file/job/$job->id/table.csv");
                $data['content'] = view('results/phylobar', $data)->render();
                break;
            case 'heatcloud':
                $data['table_csv'] = url("/storage/get_job_file/job/$job->id/table.csv");
                $data['content'] = view('results/heatcloud', $data)->render();
                break;
            case 'metamds_visual':
                $data['data_js'] = file($data['job_folder'] . '/data.js');
                $data['content'] = view('results/metamds_visual', $data)->render();
                break;
            case 'cca_visual':
                $data['data_js'] = file($data['job_folder'] . '/dataCCA.js');
                $data['content'] = view('results/cca_visual', $data)->render();
                break;
            case 'mapping_tools_visual':
                $data['data_js'] = file($data['job_folder'] . '/dataMap.js');
                $data['content'] = view('results/mapping_tools_visual', $data)->render();
                break;
            case 'mapping_tools_div_visual':
                $data['data_js'] = file($data['job_folder'] . '/dataMapDiv.js');
                $data['content'] = view('results/mapping_tools_div_visual', $data)->render();
                break;
        }

        return true;
    }

    /**
     * Parse the text/html result from an analysis
     *
     * @param string $outputFile
     * @param array $data
     * @return mixed
     */
    protected function parseOutput($outputFile, array $data)
    {
        $parser = new RvlabParser();
        $parser->parse_output($data['job_folder'].'/'.$outputFile);

        if ($parser->hasFailed()) {
            $data['errorString'] = $parser->getOutput();
            return false;
        }

        return $parser->getOutput();
    }

    /**
     * Parse the text/html result from a BICT analysis
     *
     * @param array $data
     * @return mixed
     */
    protected function parseBictOutput(array $data)
    {
        $parser = new RvlabParser();
        $parser->parse_output($data['job_folder'] . '/cmd_line_output.txt');

        if ($parser->hasFailed()) {
            $data['errorString'] = $parser->getOutput();
            return false;
        } else {
            if (file_exists($data['job_folder'] . "/indices.txt")) {
                $handle = fopen($data['job_folder'] . "/indices.txt", "r");

                if ($handle) {
                    while (($textline = fgets($handle)) !== false) {
                        $results .= $textline . "<br>";
                    }
                    fclose($handle);
                }
            }
        }

        return $parser->getOutput();
    }
}
