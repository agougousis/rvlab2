<?php

namespace App\RAnalysis;

use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes a hclust analysis
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
class hclust extends BaseAnalysis implements RAnalysis {

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
     * The method_select parameter
     *
     * @var string
     */
    private $method_select;

    /**
     * The column_select parameter
     *
     * @var string
     */
    private $column_select;

    /**
     * Initializes class properties
     */
    protected function init() {
        $this->formValidationRules = [
            'box' => 'required|string|max:250',
            'box2' => 'string|max:250',
            'method_select' => 'required|string|max:250',
            'column_select' => 'required_with:box2|string|max:250'
        ];
    }

    /**
     * Runs a hclust analysis
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

        if (!empty($this->box2)) {
            $workspace_filepath = $this->user_workspace . '/' . $this->box2;
            $job_filepath = $this->job_folder . '/' . $this->box2;

            if (!copy($workspace_filepath, $job_filepath)) {
                throw new Exception('Moving file from workspace to job folder, failed.');
            }
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

        if (!empty($this->form['box2'])) {
            $this->box2 = $this->form['box2'];
            $this->inputs .= ";" . $this->box2;
        } else {
            $this->box2 = "";
        }

        if (!empty($this->form['column_select'])) {
            $this->column_select = $this->form['column_select'];
            $this->params .= ";column_select:" . $this->column_select;
        } else {
            $this->params .= ";column_select: ";
        }

        $this->method_select = $this->form['method_select'];
        $this->params .= ";method_select:" . $this->method_select;
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
        fwrite($fh, "library(dendextend);\n");
        fwrite($fh, "dist <- get(load(\"$this->remote_job_folder/$this->box\"));\n");
        fwrite($fh, "clust.average <- hclust(dist, method = \"$this->method_select\")\n");
        fwrite($fh, "dend <- as.dendrogram(clust.average);\n");

        if(!empty($this->box2)){
            fwrite($fh, "Groups <- read.table(\"$this->remote_job_folder/$this->box2\", header = TRUE, sep=\",\" ,row.names=1);\n");
            fwrite($fh, "groupCodes <- Groups\$$this->column_select;\n");
            fwrite($fh, "# Assigning the labels of dendrogram object with new colors:;\n");
            fwrite($fh, "labels_cols <- rainbow(length(groupCodes))[rank(groupCodes)];\n");
            fwrite($fh, "labels_cols <- labels_cols[order.dendrogram(dend)];\n");
            fwrite($fh, "groupCodes <- groupCodes[order.dendrogram(dend)];\n");
            fwrite($fh, "labels_colors(dend) <- labels_cols;\n");
            fwrite($fh, "png('legend.png',height = 700,width=350)\n");
            fwrite($fh, "plot(dist, type = \"n\",ylab=\"\",xlab=\"\",yaxt=\"n\",xaxt=\"n\",bty=\"n\")\n");
            fwrite($fh, "legend(\"topright\", legend=unique(groupCodes), col=unique(labels_cols), pch = 16);\n");
            fwrite($fh, "dev.off()\n");
        }

        fwrite($fh, "png('rplot.png',height = 600, width = 600)\n");
        fwrite($fh, "plot(dend)\n");
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "summary(clust.average);\n");
        fclose($fh);

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
        fwrite($fh2, "/usr/bin/R CMD BATCH $this->remote_job_folder/$this->job_id.R > $this->remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);
    }
}