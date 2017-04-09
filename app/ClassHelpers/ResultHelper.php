<?php

namespace App\ClassHelpers;

use App\Models\Job;
use App\ClassHelpers\JobOutputParser;

/**
 * Handles subtasks related to building a job's results page
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class ResultHelper
{
    /**
     * Defines the main output file that was prodused by an analysis
     *
     * @param string $jobFunction
     * @param array $data
     */
    public function loadMainResultFilename($jobFunction, array &$data)
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
    public function loadResultImages($jobFunction, array &$data)
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
    public function loadResultHtml(Job $job, array &$data)
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
        $parser = new JobOutputParser();
        $parser->parseOutput($data['job_folder'].'/'.$outputFile);

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
        $parser = new JobOutputParser();
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
