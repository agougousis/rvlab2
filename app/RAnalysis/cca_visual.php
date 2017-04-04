<?php

namespace App\RAnalysis;

use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes an cca_visual analysis
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
class cca_visual extends BaseAnalysis implements RAnalysis
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
     * The Factor_select1 parameter
     *
     * @var string
     */
    private $factor_select1;

    /**
     * The Factor_select2 parameter
     *
     * @var string
     */
    private $factor_select2;

    /**
     * The Factor_select3 parameter
     *
     * @var string
     */
    private $factor_select3;

    /**
     * The top_species parameter
     *
     * @var int
     */
    private $top_species;

    /**
     * Initializes class properties
     */
    protected function init()
    {
        $this->formValidationRules = [
            'box'       =>  'required|string|max:250',
            'box2'      =>  'required|string|max:250',
            'transpose' => 'string|max:250',
            'transf_method_select'  =>  'required|string|max:250',
            'Factor_select1'    => 'required|string|max:250',
            'Factor_select2'    => 'required|string|max:250',
            'Factor_select3'    =>  'string|max:250',
            'top_species'       =>  'required|int'
        ];
    }

    /**
     * Runs a cca_visual analysis
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

        $workspace_filepath = $this->user_workspace . '/' . $this->box2;
        $job_filepath = $this->job_folder . '/' . $this->box2;

        if (!copy($workspace_filepath, $job_filepath)) {
            throw new Exception('Moving file from workspace to job folder, failed.');
        }

        $script_source = app_path().'/rvlab/files/summarizeCCA.html';
        if (!copy($script_source,"$this->job_folder/summarizeCCA.html")) {
            throw new Exception('Moving html file to job folder, failed.');
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

        if (empty($this->form['transpose'])) {
            $this->transpose = "";
            $this->params .= ";transpose: ";
        } else {
            $this->transpose = $this->form['transpose'];
            $this->params .= ";transpose:" . $this->transpose;
        }

        $this->transf_method_select = $this->form['transf_method_select'];
        $this->params .= ";transf_method_select:" . $this->transf_method_select;

        $this->factor_select1 = $this->form['Factor_select1'];
        $this->params .= ";Factor_select1:" . $this->factor_select1;

        $this->factor_select2 = $this->form['Factor_select2'];
        $this->params .= ";Factor_select2:" . $this->factor_select2;

        $this->factor_select3 = $this->form['Factor_select3'];
        if (empty($this->factor_select3)) {
            $this->params .= ";Factor_select3: ";
        } else {
            $this->params .= ";Factor_select3:" . $this->factor_select3;
        }

        $this->top_species = $this->form['top_species'];
        $this->params .= ";top species:".$this->top_species;
    }

    /**
     * Builds the required R script for the job execution
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
        fwrite($fh, "x <- read.table(\"$this->remote_job_folder/$this->box\", header = TRUE, sep=\",\",row.names=1);\n");
        fwrite($fh, "ENV <- read.table(\"$this->remote_job_folder/$this->box2\",header = TRUE, sep=\",\",row.names=1);\n");
        if ($this->transpose == "transpose") {
            fwrite($fh, "x <- t(x);\n");
        }

        if ($this->transf_method_select != "none") {
            fwrite($fh, "x <- decostand(x, method = \"$this->transf_method_select\");\n");
        }
        fwrite($fh, "rownames(x) <- gsub(\"\\\.\",\"_\",gsub(\" \",\"_\",rownames(x)));\n");//new
        if (empty($this->factor_select3)) {
            fwrite($fh, "vare.cca <- cca(x ~ $this->factor_select1+$this->factor_select2, data=ENV);\n");
        } else {
            fwrite($fh, "vare.cca <- cca(x ~ $this->factor_select1+$this->factor_select2+$this->factor_select3, data=ENV);\n");
        }


        fwrite($fh,"cca1.plot<-plot(vare.cca, type=\"n\");\n");
        fwrite($fh,"text(vare.cca, dis=\"cn\");\n");
        fwrite($fh,"points(vare.cca, pch=21, col=\"red\", bg=\"red\", cex=0.5);\n");

        fwrite($fh, "x<-x/rowSums(x);\n");
        fwrite($fh, "x<-x[,order(colSums(x),decreasing=TRUE)];\n");
        fwrite($fh, "#Extract list of top N Taxa;\n");
        fwrite($fh, "N<-$this->top_species;\n");
        fwrite($fh, "taxa_list<-colnames(x)[1:N];\n");
        fwrite($fh, "#remove \"__Unknown__\" and add it to others;\n");
        fwrite($fh, "taxa_list<-taxa_list[!grepl(\"__Unknown__\",taxa_list)];\n");
        fwrite($fh, "N<-length(taxa_list);\n");
        fwrite($fh, "new_x<-data.frame(x[,colnames(x) %in% taxa_list],Others=rowSums(x[,!colnames(x) %in% taxa_list]));\n");
        fwrite($fh, "names<-gsub(\"\\\.\",\"_\",gsub(\" \",\"_\",colnames(new_x)));\n");
	fwrite($fh, "rownames(new_x) <- gsub(\"\\\.\",\"_\",gsub(\" \",\"_\",rownames(new_x)));\n");//new
        fwrite($fh, "sink(\"dataCCA.js\");\n");
        fwrite($fh, "cat(\"var freqData=[\\n\");\n");
        fwrite($fh, "for (i in (1:dim(new_x)[1])){  \n");
        fwrite($fh, "  cat(paste(\"{Samples:\'\",rownames(new_x)[i],\"\',\",sep=\"\"));\n");
        fwrite($fh, "  cat(paste(\"freq:{\",paste(paste(names,\":\",new_x[i,],sep=\"\"),collapse=\",\"),\"},\",sep=\"\"));\n");
        fwrite($fh, "  cat(paste(\"MDS:{\",paste(paste(colnames(cca1.plot\$sites),cca1.plot\$sites[rownames(new_x)[i],],sep=\":\"),collapse=\",\"),\"}}\\n\",sep=\"\"));\n");
        fwrite($fh, "  if(i!=dim(new_x)[1]){cat(\",\")};\n");
        fwrite($fh, "};\n");
        fwrite($fh, "cat(\"];\\n\\n\");\n");
        fwrite($fh, "cat(\"var biplot=[\\n\");\n");
        fwrite($fh, "for (i in (1:dim(cca1.plot\$biplot)[1])){;\n");
        fwrite($fh, "  cat(paste(\"{fact:{\",paste(paste(colnames(cca1.plot\$biplot),cca1.plot\$biplot[rownames(cca1.plot\$biplot)[i],],sep=\":\"),collapse=\",\"),\"}}\\n\",sep=\"\"))  ;\n");
        fwrite($fh, "  if(i!=dim(cca1.plot\$biplot)[1]){cat(\",\")};\n");
        fwrite($fh, "};\n");
        fwrite($fh, "cat(\"];\\n\\n\");\n");
        fwrite($fh, "cat(\"var biplotLabels=[\\n\");\n");
        fwrite($fh, "for (i in (1:dim(cca1.plot\$biplot)[1])){;\n");
        fwrite($fh, "  cat(paste(\"{fact:\\\"\",paste(paste(rownames(cca1.plot\$biplot)[i],sep=\":\"),collapse=\",\"),\"\\\"}\\n\",sep=\"\"));\n");
        fwrite($fh, "  if(i!=dim(cca1.plot\$biplot)[1]){cat(\",\")};\n");
        fwrite($fh, "};\n");
        fwrite($fh, "cat(\"];\\n\");\n");
        fwrite($fh, "sink();\n");

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
