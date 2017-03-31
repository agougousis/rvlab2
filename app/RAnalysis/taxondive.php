<?php

namespace App\RAnalysis;

use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes a taxondive analysis
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
class taxondive extends BaseAnalysis implements RAnalysis
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
     * The third input file to be used for the analysis
     *
     * @var string
     */
    private $box3;

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
     * The deltalamda parameter
     *
     * @var string
     */
    private $deltalamda;

    /**
     * The match_force parameter
     *
     * @var string
     */
    private $match_force;

    /**
     * Initializes class properties
     */
    protected function init()
    {
        $this->formValidationRules = [
            'box' => 'required|string|max:250',
            'box2' => 'required|string|max:250',
            'box3' => 'string|max:250',
            'transpose' => 'string|max:250',
            'transf_method_select'  =>  'required|string|max:250',
            'column_select' => 'required|string|max:250',
            'deltalamda' => 'required|string|in:Delta,Lamda',
            'match_force' => 'required|string|in:TRUE,FALSE'
        ];
    }

    /**
     * Runs a taxondive analysis
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

        $workspace_filepath = $this->user_workspace . '/' . $this->box2;
        $job_filepath = $this->job_folder . '/' . $this->box2;

        if (!copy($workspace_filepath, $job_filepath)) {
            throw new Exception('Moving file from workspace to job folder, failed.');
        }

        if (!empty($form['box3'])) {
            $workspace_filepath = $this->user_workspace . '/' . $this->box3;
            $job_filepath = $this->job_folder . '/' . $this->box3;

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

        $this->box2 = $this->form['box2'];
        $this->inputs .= ";" . $this->box2;

        if (!empty($form['box3'])) {
            $this->box3 = $this->form['box3'];
            $this->inputs .= ";" . $this->box3;
        }

        if (empty($this->form['transpose'])) {
            $this->transpose = "";
            $this->params .= ";transpose: ";
        } else {
            $this->transpose = $this->form['transpose'];
            $this->params .= ";transpose:" . $this->transpose;
        }

        $this->transf_method_select = $this->form['transf_method_select'];
        $this->params .= ";transf_method_select:" . $this->transf_method_select;

        if (!empty($this->form['column_select'])) {
            $this->column_select = $this->form['column_select'];
            $this->params .= ";column_select:" . $this->column_select;
        } else {
            $this->column_select = "";
            $this->params .= ";column_select: ";
        }

        $this->deltalamda = $this->form['deltalamda'];
        $this->params .= ";deltalamda:" . $this->deltalamda;

        $this->match_force = $this->form['match_force'];
        $this->params .= ";match_force:" . $this->match_force;
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
        fwrite($fh, "taxdis <- get(load(\"$this->remote_job_folder/$this->box2\"));\n");
        fwrite($fh, "mat <- read.table(\"$this->remote_job_folder/$this->box\", header = TRUE, sep=\",\" ,row.names=1);\n");

        if(!empty($this->transpose)){
            fwrite($fh, "mat <- t(mat);\n");
        }

        if($this->transf_method_select != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$this->transf_method_select\");\n");
        }

        fwrite($fh, "taxondive <- taxondive(mat,taxdis,match.force=$this->match_force);\n");
        fwrite($fh, "save(taxondive, ascii=TRUE, file = \"$this->remote_job_folder/taxondive.csv\");\n");

        if(empty($this->box3)){
            fwrite($fh, "labels <- as.factor(rownames(mat));\n");
            fwrite($fh, "n<- length(labels);\n");
            fwrite($fh, "rain <- rainbow(n, s = 1, v = 1, start = 0, end = max(1, n - 1)/n, alpha = 0.8);\n");
            fwrite($fh, "labels <- rain;\n");
        }else{
            fwrite($fh, "ENV <- read.table(\"$this->remote_job_folder/$this->box3\", header = TRUE, sep=\",\" ,row.names=1);\n");
            fwrite($fh, "labels <- as.factor(ENV\$$this->column_select);\n");
        }
        fwrite($fh, "png('legend.png',height = 700, width = 350)\n");
        fwrite($fh, "plot(mat, type = \"n\",ylab=\"\",xlab=\"\",yaxt=\"n\",xaxt=\"n\",bty=\"n\")\n");

        if(empty($this->box3)){
            fwrite($fh, "legend(\"topright\", legend=rownames(mat), col=labels, pch = 16);\n");
        }else{
            fwrite($fh, "legend(\"topright\", legend=unique(ENV\$$this->column_select), col=unique(labels), pch = 16);\n");
        }
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "png('rplot.png',height = 600, width = 600)\n");

        if($this->deltalamda=="Delta"){
            fwrite($fh, "if(min(taxondive\$Dplus) < min(taxondive\$EDplus-taxondive\$sd.Dplus*2)){\n");
            fwrite($fh, "plot(taxondive,pch=19,col=labels,cex = 1.7, ylim = c(min(taxondive\$Dplus),max(taxondive\$sd.Dplus*2+taxondive\$EDplus)), xlim = c(min(taxondive\$Species),max(taxondive\$Species)));\n");
            fwrite($fh, "}else if(max(taxondive\$Dplus) > max(taxondive\$sd.Dplus*2+taxondive\$EDplus)){\n");
            fwrite($fh, "plot(taxondive,pch=19,col=labels,cex = 1.7, ylim = c(min(taxondive\$EDplus-taxondive\$sd.Dplus*2),max(taxondive\$Dplus)), xlim = c(min(taxondive\$Species),max(taxondive\$Species)))\n");
            fwrite($fh, "}else{\n");
            fwrite($fh, "plot(taxondive,pch=19,col=labels,cex = 1.7,xlim = c(min(taxondive\$Species),max(taxondive\$Species)), ylim = c(min(taxondive\$EDplus-taxondive\$sd.Dplus*2),max(taxondive\$sd.Dplus*2+taxondive\$EDplus)))\n");
            fwrite($fh, "}\n");#
            fwrite($fh, "with(taxondive, text(Species-.3, Dplus-1, as.character(rownames(mat)),pos = 4, cex = 0.9))\n");
            fwrite($fh, "dev.off()\n");
            fwrite($fh, "summary(taxondive);\n");
        }else{
            fwrite($fh, "lambda1 <- taxondive\$Lambda\n");
            fwrite($fh, "Species1 <- taxondive\$Species\n");
            fwrite($fh, "lambda_dat <- as.matrix(lambda1)\n");

            fwrite($fh, "colnames(lambda_dat) <- c(\"L\")\n");
            fwrite($fh, "#lambda_dat <- lambda_dat[,-1]\n");

            fwrite($fh, "Species_dat <- as.matrix(Species1)\n");
            fwrite($fh, "colnames(Species_dat) <- c(\"Species\")\n");
            fwrite($fh, "data2 <- merge(lambda_dat,Species_dat,by=\"row.names\")\n");

            fwrite($fh, "#taxondive\$Dplus <- taxondive\$Lambda\n");

            fwrite($fh, "fit <- lm(L~Species, data=data2)\n");

            fwrite($fh, "#confint(fit, 'Species', level=0.95)\n");

            fwrite($fh, "newx <- seq(min(data2\$Species), max(data2\$Species), length.out=n)\n");
            fwrite($fh, "preds <- predict(fit,  interval = 'confidence')\n");
            fwrite($fh, "order.fit <- order(preds[,1])\n");
            fwrite($fh, "preds<- preds[order.fit,]\n");

            fwrite($fh, "#preds2\n");
            fwrite($fh, "# plot\n");
            fwrite($fh, "plot(L ~ Species, data = data2, type = 'p',pch=19,cex=1.7,col=labels)\n");
            fwrite($fh, "# add fill\n");
            fwrite($fh, "#polygon(c(rev(newx), newx), c(rev(preds[ ,3]), preds[ ,2]), col = 'grey80', border = NA)\n");
            fwrite($fh, "# model\n");
            fwrite($fh, "abline(h=mean(data2\$L),col='red')\n");
            fwrite($fh, "# intervals\n");
            fwrite($fh, "lines(newx, rev(preds[ ,3]), lty = 1, col = 'red')\n");
            fwrite($fh, "lines(newx, preds[ ,2], lty = 1, col = 'red')\n");
            fwrite($fh, "dev.off()\n");
            fwrite($fh, "summary(taxondive);\n");
        }
        fwrite($fh, "summary(taxondive);\n");
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