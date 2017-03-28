<?php

namespace App\RAnalysis;

use Session;
use Validator;
use App\Contracts\RAnalysis;
use App\RAnalysis\BaseAnalysis;

/**
 * Executes a metamds_visual analysis
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
class metamds_visual extends BaseAnalysis implements RAnalysis
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
     * The method_select_viz parameter
     *
     * @var string
     */
    private $method_select_viz;

    /**
     * The k_select_viz parameter
     *
     * @var int
     */
    private $k_select_viz;

    /**
     * The trymax_viz parameter
     *
     * @var int
     */
    private $trymax_viz;

    /**
     * The top_species parameter
     *
     * @var int
     */
    private $top_species;

    /**
     * The validation rules for metamds_visual submission form
     *
     * @var array
     */
    private $formValidationRules = [
        'box' => 'required|string|max:250',
        'transpose' => 'string|max:250',
        'transf_method_select' => '',
        'method_select_viz' => 'required|string|max:250',
        'k_select_viz' => 'required|int',
        'trymax_viz' => 'required|int',
        'top_species' => 'required|int'
    ];

    /**
     * Runs a metamds_visual analysis
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

        $script_source = app_path().'/rvlab/files/summarize.html';
        copy($script_source,"$this->job_folder/summarize.html");
    }

    /**
     * Retrieves input parameters from form data
     *
     * @throws Exception
     */
    private function getInputParams()
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

        $this->method_select = $this->form['method_select_viz'];
        $this->params .= ";method_select_viz:" . $this->method_select;

        $this->K = $this->form['k_select_viz'];
        $this->params .= ";k_select_viz:" . $this->K;

        $this->trymax = $this->form['trymax_viz'];
        $this->params .= ";trymax_viz:" . $this->trymax;

        $this->top_species = $this->form['top_species'];
        $this->params .= ";top_species:" . $this->top_species;
    }

    /**
     * Builds the required executables for the job execution
     *
     * @throws Exception
     */
    private function buildRScript()
    {
        // Build the R script
        if (!($fh = fopen("$this->job_folder/$this->job_id.R", "w"))) {
            throw new \Exception("Unable to open file $this->job_folder/$this->job_id.R");
        }

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "x <- read.table(\"$this->remote_job_folder/$this->box\", header = TRUE, sep=\",\",row.names=1);\n");

        if($this->transpose == "transpose"){
            fwrite($fh, "x <- t(x);\n");
        }

        if($this->transformation_method != "none"){
            fwrite($fh, "x <- decostand(x, method = \"$this->transformation_method\");\n");
        }

        fwrite($fh, "MDS<-metaMDS(x, distance = \"$this->method_select\", k = $$this->K, trymax = $this->trymax);\n");
        fwrite($fh, "x<-x/rowSums(x);\n");
        fwrite($fh, "x<-x[,order(colSums(x),decreasing=TRUE)];\n");
        fwrite($fh, "#Extract list of top N Taxa;\n");
        fwrite($fh, "N<-$this->top_species;\n");
        fwrite($fh, "taxa_list<-colnames(x)[1:N];\n");
        fwrite($fh, "#remove \"__Unknown__\" and add it to others;\n");
        fwrite($fh, "taxa_list<-taxa_list[!grepl(\"__Unknown__\",taxa_list)];\n");
        fwrite($fh, "N<-length(taxa_list);\n");
        fwrite($fh, "new_x<-data.frame(x[,colnames(x) %in% taxa_list],Others=rowSums(x[,!colnames(x) %in% taxa_list]));\n");
        fwrite($fh, "new_x2 <- t(new_x);\n");
        fwrite($fh, "write.table(new_x2, file = \"$this->remote_job_folder/filtered_abundance.csv\",sep=\",\",quote = FALSE,row.names = TRUE,col.names=NA);\n");
        fwrite($fh, "names<-gsub(\"\\\.\",\"_\",gsub(\" \",\"_\",colnames(new_x)));\n");
        fwrite($fh, "sink(\"data.js\");\n");
        fwrite($fh, "cat(\"var freqData=[\\n\");\n");
        fwrite($fh, "for (i in (1:dim(new_x)[1])){  \n");
        fwrite($fh, "  cat(paste(\"{Samples:\'\",rownames(new_x)[i],\"\',\",sep=\"\"));\n");
        fwrite($fh, "  cat(paste(\"freq:{\",paste(paste(names,\":\",new_x[i,],sep=\"\"),collapse=\",\"),\"},\",sep=\"\"));\n");
        fwrite($fh, "  cat(paste(\"MDS:{\",paste(paste(colnames(MDS\$points),MDS\$points[rownames(new_x)[i],],sep=\":\"),collapse=\",\"),\"}}\\n\",sep=\"\"));\n");
        fwrite($fh, "  if(i!=dim(new_x)[1]){cat(\",\")};\n");
        fwrite($fh, "  };\n");
        fwrite($fh, "          cat(\"];\");\n");
        fwrite($fh, "  sink();\n");
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
