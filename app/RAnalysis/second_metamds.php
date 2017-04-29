<?php

namespace App\RAnalysis;

use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes a second_metamds analysis
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
class second_metamds extends BaseAnalysis implements RAnalysis
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
     * The method_select parameter
     *
     * @var string
     */
    private $method_select;

    /**
     * The cor_method_select parameter
     *
     * @var string
     */
    private $cor_method_select;

    /**
     * The k_select parameter
     *
     * @var int
     */
    private $k_select;

    /**
     * The trymax parameter
     *
     * @var int
     */
    private $trymax;

    /**
     * The autotransform_select parameter
     *
     * @var string
     */
    private $autotransform_select;

    /**
     * The noshare parameter
     *
     * @var float
     */
    private $noshare;

    /**
     * The warscores_select parameter
     *
     * @var string
     */
    private $wascores_select;

    /**
     * The expand parameter
     *
     * @var string
     */
    private $expand;

    /**
     * The trace parameter
     *
     * @var int
     */
    private $trace;

    /**
     * Initializes class properties
     */
    protected function init()
    {
        $this->formValidationRules = [
            'box' => 'required|max:10',
            'transpose' => 'string|max:250',
            'transf_method_select' => '',
            'cor_method_select' => 'required|string|max:250',
            'method_select' => 'required|string|max:250',
            'column_select' => 'required_with:box2|string|max:250',
            'k_select' => 'required|int',
            'trymax' => 'required|int',
            'autotransform_select' => 'required|string|in:TRUE,FALSE',
            'noshare' => 'required|numeric',
            'wascores_select' => 'required|string|in:TRUE,FALSE',
            'expand' => 'required|string|in:TRUE,FALSE',
            'trace' => 'required|int'
        ];
    }

    /**
     * Moved input files from workspace to job's folder
     *
     * @throws Exception
     */
    protected function copyInputFiles()
    {
        foreach ($this->box as $box_file) {
            $workspace_filepath = $this->user_workspace . '/' . $box_file;
            $job_filepath = $this->job_folder . '/' . $box_file;

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
        $this->params .= ";transf_method_select:" . $this->transf_method_select;

        $this->cor_method_select = $this->form['cor_method_select'];
        $this->params .= ";cor_method_select:" . $this->cor_method_select;

        $this->method_select = $this->form['method_select'];
        $this->params .= ";method_select:" . $this->method_select;

        $this->k_select = $this->form['k_select'];
        $this->params .= ";k_select:" . $this->k_select;

        $this->trymax = $this->form['trymax'];
        $this->params .= ";trymax:" . $this->trymax;

        $this->autotransform_select = $this->form['autotransform_select'];
        $this->params .= ";autotransform_select:" . $this->autotransform_select;

        $this->noshare = $this->form['noshare'];
        $this->params .= ";noshare:" . $this->noshare;

        $this->wascores_select = $this->form['wascores_select'];
        $this->params .= ";wascores_select:" . $this->wascores_select;

        $this->expand = $this->form['expand'];
        $this->params .= ";expand:" . $this->expand;

        $this->trace = $this->form['trace'];
        $this->params .= ";trace:" . $this->trace;
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
        fwrite($fh, "library(ecodist);\n");
        $filecount = 1;
        fwrite($fh, "# replace missing data with 0;\n");
        fwrite($fh, "# fourth root or any other transformation here, excluding first column with taxon names;\n");
        fwrite($fh, "#transpose the matrices, bcdist needs rows as samples;\n");
        fwrite($fh, "# calculate bray curtis for all;\n");
        foreach ($this->box as $val) {
            fwrite($fh, "mat".$filecount." <- read.table(\"$this->remote_job_folder/$val\", header = TRUE, sep=\",\",row.names=1);\n");
            if ($this->transpose == "transpose") {
                fwrite($fh, "mat".$filecount." <- t(mat".$filecount.");\n");
            }

            if ($this->transf_method_select != "none") {
                fwrite($fh, "mat".$filecount." <- decostand(mat".$filecount.", method = \"$this->transf_method_select\");\n");
            }

            fwrite($fh, "mat".$filecount."[is.na(mat".$filecount.")]<-0;\n");
            fwrite($fh, "mat".$filecount."_2 <- sqrt(sqrt(mat".$filecount."));\n");//[,-1]
            fwrite($fh, "mat".$filecount."_tr <- t(mat".$filecount."_2);\n");
            fwrite($fh, "bc".$filecount." <-bcdist(mat".$filecount."_tr);\n");
            $filecount++;
        }
        fwrite($fh, "#create an empty matrix to fill in the correlation coefficients;\n");
        $filecount--;
        fwrite($fh, "bcs <- matrix(NA, ncol=".$filecount.", nrow=".$filecount.");\n");
        fwrite($fh,"combs <- combn(1:$filecount, 2);\n");
        fwrite($fh,"for (i in 1:ncol(combs) ) {\n");
        fwrite($fh, "bc1_t <- paste(\"bc\",combs[1,i],sep=\"\");\n");
        fwrite($fh, "bc2_t <- paste(\"bc\",combs[2,i],sep=\"\");\n");
        fwrite($fh, "bcs[combs[1,i],combs[2,i]] <- cor(get(bc1_t), get(bc2_t), method=\"$this->cor_method_select\");\n");
        fwrite($fh, "}\n");
        fwrite($fh, "bcs <- t(bcs)\n");
        fwrite($fh, "x <- c(\"{$this->box[0]}\");\n");

        for ($j=1; $j<count($this->box); $j++) {
            fwrite($fh, "x <- append(x, \"{$this->box[$j]}\");\n");
        }
        fwrite($fh, "colnames(bcs) <-x;\n");
        fwrite($fh, "rownames(bcs) <-x;\n");
        fwrite($fh, "#transform the matrix into a dissimlarity matrix of format \"dis\";\n");
        fwrite($fh, "dist1 <- as.dist(bcs, diag = FALSE, upper = FALSE);\n");

        fwrite($fh, "#dist2 <- as.dist(NA);\n");
        fwrite($fh, "dist2<- 1-dist1;\n");

        fwrite($fh, "#run the mds;\n");
        fwrite($fh, "mydata.mds<- metaMDS(dist2,  k = $this->k_select, trymax = $this->trymax,distance=\"$this->method_select\");\n");
        fwrite($fh, "save(dist2, ascii=TRUE, file = \"$this->remote_job_folder/dist_2nd_stage.csv\");\n");

        fwrite($fh, "png('legend.png',height = 700,width=350)\n");
        fwrite($fh, "plot(mydata.mds, type = \"n\",ylab=\"\",xlab=\"\",yaxt=\"n\",xaxt=\"n\",bty=\"n\")\n");
        fwrite($fh, "n<- length(x);\n");
        fwrite($fh, "rain <- rainbow(n, s = 1, v = 1, start = 0, end = max(1, n - 1)/n, alpha = 0.8);\n");
        fwrite($fh, "labels <- rain;\n");
        fwrite($fh, "legend(\"topright\", legend=x, col=labels, pch = 16);\n");
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "#plot the empty plot;\n");
        fwrite($fh, "png('rplot.png',height = 600,width=600)\n");
        fwrite($fh, "plot(mydata.mds, type=\"n\");\n");
        fwrite($fh, "par(mar=c(5.1, 8.1, 4.1, 8.1), xpd=TRUE);\n");
        fwrite($fh, "#add the points for the stations, blue with red circle;\n");
        fwrite($fh, "points(mydata.mds, display = c(\"sites\", \"species\"), cex = 1.8, pch=19, col=labels);\n");
        fwrite($fh, "# add the labels for the stations;\n");
        fwrite($fh, "text(mydata.mds, display = c(\"sites\", \"species\"), cex = 1.0 , pos=3 );\n");
        fwrite($fh, "dev.off()\n");

        fwrite($fh, "#alternative plotting:;\n");
        fwrite($fh, "#ordipointlabel(mydata.mds, display =\"spec\");\n");
        fwrite($fh, "#points(mydata.mds, display = \"spec\", cex = 1.0, pch=20, col=\"red\", type=\"t\"');\n");

        fwrite($fh, "#alternative plotting - allows to drag the labels to a better position and then export the graphic as EPS;\n");
        fwrite($fh, "#orditkplot(mydata.mds) ;\n");
        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "mydata.mds;\n");
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
