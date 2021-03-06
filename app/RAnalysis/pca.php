<?php

namespace App\RAnalysis;

use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes a pca analysis
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
class pca extends BaseAnalysis implements RAnalysis
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
     * The column_select parameter
     *
     * @var string
     */
    private $column_select;

    /**
     * Initializes class properties
     */
    protected function init()
    {
        $this->formValidationRules = [
            'box' => 'required|string|max:250',
            'box2' => 'string|max:250',
            'transpose' =>  'string|max:250',
            'transf_method_select' => 'required|string|max:250',
            'column_select' => 'required_with:box2|string|max:250'
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

        if (!empty($this->form['box2'])) {
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

        if (empty($this->form['transpose'])) {
            $this->transpose = "";
            $this->params .= ";transpose: ";
        } else {
            $this->transpose = $this->form['transpose'];
            $this->params .= ";transpose:" . $this->transpose;
        }

        $this->transf_method_select = $this->form['transf_method_select'];
        $this->params .= ";transofrmation method:" . $this->transf_method_select;

        if (!empty($this->form['box2'])) {
            $this->box2 = $this->form['box2'];
            $this->inputs .= ";" . $this->box2;

            $this->column_select = $this->form['column_select'];
            $this->params .= ";column_select:" . $this->column_select;
        } else {
            $this->box2 = "";
            $this->params .= ";column_select: ";
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

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "mat <- read.table(\"$this->remote_job_folder/$this->box\", header = TRUE, sep=\",\",row.names=1);\n");

        if ($this->transpose == "transpose") {
            fwrite($fh, "mat <- t(mat);\n");
        }

        if ($this->transf_method_select != "none") {
            fwrite($fh, "mat <- decostand(mat, method = \"$this->transf_method_select\");\n");
        }

        if (empty($this->form['box2'])) {
            fwrite($fh, "labels <- as.factor(rownames(mat));\n");
            fwrite($fh, "n<- length(labels);\n");
            fwrite($fh, "rain <- rainbow(n, s = 1, v = 1, start = 0, end = max(1, n - 1)/n, alpha = 0.8);\n");
            fwrite($fh, "labels <- rain;\n");
        } else {
            fwrite($fh, "ENV <- read.table(\"$this->remote_job_folder/$this->box2\", header = TRUE, sep=\",\" ,row.names=1);\n");
            fwrite($fh, "labels <- as.factor(ENV\$$this->column_select);\n");
        }

        fwrite($fh, "otu.pca <- rda(mat);\n");
        fwrite($fh, "par(xpd=TRUE);\n");
        fwrite($fh, "png('$this->remote_job_folder/legend.png',height = 700,width=350)\n");
        fwrite($fh, "plot(otu.pca, type = \"n\",ylab=\"\",xlab=\"\",yaxt=\"n\",xaxt=\"n\",bty=\"n\")\n");
        fwrite($fh, "abline(h=0,col=\"white\",lty=1,lwd=3);\n");
        fwrite($fh, "abline(v=0,col=\"white\",lty=1,lwd=3);\n");

        if (empty($this->form['box2'])) {
            fwrite($fh, "legend(\"topright\", legend=rownames(mat), col=labels, pch = 16);\n");
        } else {
            fwrite($fh, "legend(\"topright\", legend=unique(ENV\$$this->column_select), col=unique(labels), pch = 16);\n");
        }

        fwrite($fh, "dev.off()\n");
        fwrite($fh, "png('$this->remote_job_folder/rplot.png',height = 600,width=600)\n");
        fwrite($fh, "plot(otu.pca, type = \"n\")\n");
        fwrite($fh, "points(otu.pca, col = labels, pch = 16,cex = 1.7);\n");
        fwrite($fh, "dev.off()\n");

        fwrite($fh, "pdf(file='$this->remote_job_folder/rplot.pdf',width=10, height=10)\n");
        fwrite($fh, "plot(otu.pca, type = \"n\")\n");
        fwrite($fh, "points(otu.pca, col = labels, pch = 16,cex = 1.7);\n");
        fwrite($fh, "plot(otu.pca, type = \"n\",ylab=\"\",xlab=\"\",yaxt=\"n\",xaxt=\"n\",bty=\"n\")\n");
        fwrite($fh, "abline(h=0,col=\"white\",lty=1,lwd=3);\n");
        fwrite($fh, "abline(v=0,col=\"white\",lty=1,lwd=3);\n");

        if (empty($this->form['box2'])) {
            fwrite($fh, "legend(\"topright\", legend=rownames(mat), col=labels, pch = 16);\n");
        } else {
            fwrite($fh, "legend(\"topright\", legend=unique(ENV\$$this->column_select), col=unique(labels), pch = 16);\n");
        }
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "otu.pca;\n");
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
