<?php

namespace App\Http\Controllers;

use Session;
use App\Http\Controllers\JobController;
use Illuminate\Foundation\Application;

/**
 * ....
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class VisualController extends JobController {

    public function __construct() {
        parent::__construct();
    }

    /**
     * Handles the part of job submission functionlity that relates to anosim function.
     *
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @param string $params A string designated to contain the input parameterers used by this job
     * @return boolean
     */
    protected function anosim($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        $box= $form['box'];
        $box2 = $form['box2'];
        $inputs .= ";".$box2;
        $transformation_method = $form['transf_method_select'];

        if(empty($form['column_select'])){
            Session::flash('toastr',array('error','You forgot to select factor column!'));
            return false;
        } else {
            $column_select = $form['column_select'];
        }

        if(empty($form['permutations'])){
            Session::flash('toastr',array('error','You forgot to set permutations!'));
            return false;
        } else {
            $permutations = $form['permutations'];
        }

        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the method!'));
            return false;
        } else {
            $method_select = $form['method_select'];
        }

        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }

        $params .= ";transpose:".$transpose;
        $params .= ";transf_method_select:".$transformation_method;
        $params .= ";column_select:".$column_select;
        $params .= ";permutations:".$permutations;
        $params .= ";method_select:".$method_select;

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))
                exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box2\",header = TRUE, sep=\",\",row.names=1);\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\" ,row.names=1);\n");

        if($transpose == "transpose"){
            fwrite($fh, "mat <- t(mat);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }

        fwrite($fh, "otu.ENVFACT.anosim <- anosim(mat,ENV\$$column_select,permutations = $permutations,distance = \"$method_select\");\n");
        fwrite($fh, "png('rplot.png')\n");
        fwrite($fh, "plot(otu.ENVFACT.anosim)\n");
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "otu.ENVFACT.anosim\n");
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))
                exit("Unable to open file: $job_folder/$job_id.pbs");

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to anova function.
     *
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @param string $params A string designated to contain the input parameterers used by this job
     * @return boolean
     */
    protected function anova($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        $box= $form['box'];

        if(empty($form['one_or_two_way'])){
            Session::flash('toastr',array('error','You forgot to select between one-way and two-way anova!'));
            return false;
        } else {
            $one_or_two_way = $form['one_or_two_way'];
        }

        if(empty($form['Factor_select1'])){
            Session::flash('toastr',array('error','You forgot to select Factor 1!'));
            return false;
        } else {
            $Factor1 = $form['Factor_select1'];
        }

        if(empty($form['Factor_select2'])){
            Session::flash('toastr',array('error','You forgot to select Factor 2!'));
            return false;
        } else {
            $Factor2 = $form['Factor_select2'];
        }

        if(!empty($form['Factor_select3'])){
            $Factor3 = $form['Factor_select3'];
        } else {
            $Factor3 = "";
        }

        $params .= ";formula:".$one_or_two_way;
        $params .= ";factor column 1:".$Factor1;
        $params .= ";factor column 2:".$Factor2;
        $params .= ";factor column 3:".$Factor3;

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))
                exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "library(stats);\n");
        fwrite($fh, "geo <- read.table(\"$remote_job_folder/$box\", row.names=1, header = TRUE, sep=\",\");\n");

        if($one_or_two_way =="one"){
            fwrite($fh, "aov.ex1<-aov($Factor1~$Factor2,geo);\n");
            fwrite($fh, "png('rplot.png')\n");
            fwrite($fh, "boxplot($Factor1~$Factor2,geo,xlab=\"$Factor2\", ylab=\"$Factor1\")\n");
            fwrite($fh, "dev.off()\n");
        } else {
            fwrite($fh, "aov.ex1<-aov($Factor1~$Factor2*$Factor3,geo);\n");
            fwrite($fh, "png('rplot.png')\n");
            fwrite($fh, "boxplot($Factor1~$Factor2*$Factor3,geo)\n");
            fwrite($fh, "dev.off()\n");
        }
        fwrite($fh, "summary(aov.ex1);\n");
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))
                exit("Unable to open file: $job_folder/$job_id.pbs");

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to cca function.
     *
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @param string $params A string designated to contain the input parameterers used by this job
     * @return boolean
     */
    protected function cca($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        $box= $form['box'];
        $box2 = $form['box2'];
        $inputs .= ";".$box2;

        if(!isset($form['Factor_select1'])){
            Session::flash('toastr',array('error','You forgot to select the Factor1 column!'));
            return false;
        }

        if(!isset($form['Factor_select2'])){
            Session::flash('toastr',array('error','You forgot to select the Factor2 column!'));
            return false;
        }

        if(!isset($form['Factor_select3'])){
            Session::flash('toastr',array('error','You forgot to select the Factor3 column!'));
            return false;
        }

        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        }

        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }

        $l_1 = $form['Factor_select1'];
        $l_2 = $form['Factor_select2'];
        $l_3 = $form['Factor_select3'];
        $transformation_method = $form['transf_method_select'];

        $params .= ";factor column 1:".$l_1;
        $params .= ";factor column 2:".$l_2;
        $params .= ";factor column 3:".$l_3;
        $params .= ";transformation method:".$transformation_method;
        $params .= ";transpose:".$transpose;

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))
                exit("Unable to open file: $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", row.names=1, header = TRUE, sep=\",\");\n");
        fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box2\",header = TRUE, sep=\",\",row.names=1);\n");

        if($transpose == "transpose"){
            fwrite($fh, "mat <- t(mat);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }

        if(empty($l_3)){
            fwrite($fh, "vare.cca <- cca(mat ~ $l_1+$l_2, data=ENV);\n");
        } else {
            fwrite($fh, "vare.cca <- cca(mat ~ $l_1+$l_2+$l_3, data=ENV);\n");
        }

        fwrite($fh, "png('rplot.png',height=600,width=600)\n");
        fwrite($fh, "plot(vare.cca);\n");
        fwrite($fh, "summary(vare.cca);\n");
        fwrite($fh, "dev.off()\n");
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))
                exit("Unable to open file: $job_folder/$job_id.pbs");

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;

    }

    /**
     * Handles the part of job submission functionlity that relates to cca_visual function.
     *
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @param string $params A string designated to contain the input parameterers used by this job
     * @return boolean
     */
    protected function cca_visual($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){
        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        $box= $form['box'];
        $box2 = $form['box2'];
        $inputs .= ";".$box2;

        if(!isset($form['Factor_select1'])){
            Session::flash('toastr',array('error','You forgot to select the Factor1 column!'));
            return false;
        }

        if(!isset($form['Factor_select2'])){
            Session::flash('toastr',array('error','You forgot to select the Factor2 column!'));
            return false;
        }

        if(!isset($form['Factor_select3'])){
            Session::flash('toastr',array('error','You forgot to select the Factor3 column!'));
            return false;
        }

        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }

        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }

        if(empty($form['top_species'])){
            Session::flash('toastr',array('error','You forgot to set the number of top ranked species!'));
            return false;
        } else {
            $top_species = $form['top_species'];
        }

        $l_1 = $form['Factor_select1'];
        $l_2 = $form['Factor_select2'];
        $l_3 = $form['Factor_select3'];

        $params .= ";transpose:".$transpose;
        $params .= ";transformation method:".$transformation_method;
        $params .= ";top species:".$top_species;
        $params .= ";factor column 1:".$l_1;
        $params .= ";factor column 2:".$l_2;
        $params .= ";factor column 3:".$l_3;

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        // Move a required file
        $script_source = app_path().'/rvlab/files/summarizeCCA.html';
        copy($script_source,"$job_folder/summarizeCCA.html");

        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))
                exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "x <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\",row.names=1);\n");
        fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box2\",header = TRUE, sep=\",\",row.names=1);\n");
        if($transpose == "transpose"){
            fwrite($fh, "x <- t(x);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "x <- decostand(x, method = \"$transformation_method\");\n");
        }
        fwrite($fh, "rownames(x) <- gsub(\"\\\.\",\"_\",gsub(\" \",\"_\",rownames(x)));\n");//new
        if(empty($l_3)){
            fwrite($fh, "vare.cca <- cca(x ~ $l_1+$l_2, data=ENV);\n");
        } else {
            fwrite($fh, "vare.cca <- cca(x ~ $l_1+$l_2+$l_3, data=ENV);\n");
        }


        fwrite($fh,"cca1.plot<-plot(vare.cca, type=\"n\");\n");
        fwrite($fh,"text(vare.cca, dis=\"cn\");\n");
        fwrite($fh,"points(vare.cca, pch=21, col=\"red\", bg=\"red\", cex=0.5);\n");

        fwrite($fh, "x<-x/rowSums(x);\n");
        fwrite($fh, "x<-x[,order(colSums(x),decreasing=TRUE)];\n");
        fwrite($fh, "#Extract list of top N Taxa;\n");
        fwrite($fh, "N<-$top_species;\n");
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
        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))
                exit("Unable to open file: $job_folder/$job_id.pbs");
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to hclust function.
     *
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @param string $params A string designated to contain the input parameterers used by this job
     * @return boolean
     */
    protected function hclust($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        $box = $form['box'];

        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the Method!'));
            return false;
        } else {
            $method_select = $form['method_select'];
            $params .= ";method_select:".$method_select;
        }

        if(!empty($form['box2'])){
            $box2 = $form['box2'];
            if(!empty($form['column_select'])){
                $column_select = $form['column_select'];
                $params .= ";column_select:".$column_select;
            } else {
                Session::flash('toastr',array('error','You forgot to set the column in the factor file!'));
                return false;
            }
            $inputs .= ";".$box2;
        } else {
            $box2 = "";
            if(!empty($form['column_select'])){
                $params .= ";column_select:".$form['column_select'];;
            } else {
                $params .= ";column_select: ";
            }

        }

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        if(!empty($box2)){
            $workspace_filepath = $user_workspace.'/'.$box2;
            $job_filepath = $job_folder.'/'.$box2;
            if(!copy($workspace_filepath,$job_filepath)){
                $this->log_event('Moving file from workspace to job folder, failed.',"error");
                throw new Exception();
            }
        }

        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))
                exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "library(dendextend);\n");
        fwrite($fh, "dist <- get(load(\"$remote_job_folder/$box\"));\n");
        fwrite($fh, "clust.average <- hclust(dist, method = \"$method_select\")\n");
        fwrite($fh, "dend <- as.dendrogram(clust.average);\n");

        if(!empty($box2)){
            fwrite($fh, "Groups <- read.table(\"$remote_job_folder/$box2\", header = TRUE, sep=\",\" ,row.names=1);\n");
            fwrite($fh, "groupCodes <- Groups\$$column_select;\n");
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
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))
                exit("Unable to open file: $job_folder/$job_id.pbs");

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);


        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to heatcloud function.
     *
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @param string $params A string designated to contain the input parameterers used by this job
     * @return boolean
     */
    protected function heatcloud($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        $box= $form['box'];

        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }

        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }


        if(empty($form['top_species'])){
            Session::flash('toastr',array('error','You forgot to set the number of top ranked species!'));
            return false;
        } else {
            $top_species = $form['top_species'];
        }

        $params .= ";transpose:".$transpose;
        $params .= ";transformation method:".$transformation_method;
        $params .= ";top species:".$top_species;

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        // Move a required file
        $script_source = app_path().'/rvlab/files/HEATcloud.html';
        copy($script_source,"$job_folder/HEATcloud.html");

        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))
                exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "x <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\",row.names=1);\n");

        if($transpose == "transpose"){
            fwrite($fh, "x <- t(x);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "x <- decostand(x, method = \"$transformation_method\");\n");
        }


        fwrite($fh, "x<-x/rowSums(x);\n");
        fwrite($fh, "x<-x[,order(colSums(x),decreasing=TRUE)];\n");
        fwrite($fh, "#Extract list of top N Taxa;\n");
        fwrite($fh, "N<-$top_species;\n");
        fwrite($fh, "taxa_list<-colnames(x)[1:N];\n");
        fwrite($fh, "#remove \"__Unknown__\" and add it to others;\n");
        fwrite($fh, "taxa_list<-taxa_list[!grepl(\"__Unknown__\",taxa_list)];\n");
        fwrite($fh, "N<-length(taxa_list);\n");
        fwrite($fh, "new_x<-data.frame(x[,colnames(x) %in% taxa_list],Others=rowSums(x[,!colnames(x) %in% taxa_list]));\n");
        //fwrite($fh, "new_x2 <- t(new_x);\n");
        fwrite($fh, "new_x2 <- new_x*100;\n");
        fwrite($fh, "write.table(new_x2, file = \"$remote_job_folder/table.csv\",sep=\",\",quote = FALSE,row.names = TRUE,col.names=NA);\n");
        fwrite($fh, "names<-gsub(\"\\\.\",\"_\",gsub(\" \",\"_\",colnames(new_x)));\n");
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))
                exit("Unable to open file: $job_folder/$job_id.pbs");
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to mapping_tool_visual function.
     *
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @param string $params A string designated to contain the input parameterers used by this job
     * @return boolean
     */
    protected function mapping_tools_visual($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){
        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        $box= $form['box'];
        $box2 = $form['box2'];
        $inputs .= ";".$box2;

        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }

        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }

        if(empty($form['top_species'])){
            Session::flash('toastr',array('error','You forgot to set the number of top ranked species!'));
            return false;
        } else {
            $top_species = $form['top_species'];
        }

        $params .= ";transpose:".$transpose;
        $params .= ";transformation method:".$transformation_method;
        $params .= ";top species:".$top_species;

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        // Move a required file
        //$script_source = app_path().'/rvlab/files/World_3.jpeg';
        //copy($script_source,"$job_folder/World_3.jpeg");

        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))
                exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "x <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\",row.names=1);\n");
        fwrite($fh, "coords <- read.table(\"$remote_job_folder/$box2\",header = TRUE, sep=\",\",row.names=1);\n");
        if($transpose == "transpose"){
            fwrite($fh, "x <- t(x);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "x <- decostand(x, method = \"$transformation_method\");\n");
        }


        fwrite($fh, "x<-x/rowSums(x);\n");
        fwrite($fh, "x<-x[,order(colSums(x),decreasing=TRUE)];\n");
        fwrite($fh, "#Extract list of top N Taxa;\n");
        fwrite($fh, "N<-$top_species;\n");
        fwrite($fh, "taxa_list<-colnames(x)[1:N];\n");
        fwrite($fh, "#remove \"__Unknown__\" and add it to others;\n");
        fwrite($fh, "taxa_list<-taxa_list[!grepl(\"__Unknown__\",taxa_list)];\n");
        fwrite($fh, "N<-length(taxa_list);\n");
        fwrite($fh, "new_x<-data.frame(x[,colnames(x) %in% taxa_list],Others=rowSums(x[,!colnames(x) %in% taxa_list]));\n");
        fwrite($fh, "names<-gsub(\"\\\.\",\"_\",gsub(\" \",\"_\",colnames(new_x)));\n");
        fwrite($fh, "rownames(new_x) <- gsub(\"\\\.\",\"-\",gsub(\" \",\"_\",rownames(new_x)));\n");
        fwrite($fh, "colnames(coords)[1] <- \"Longitude\";\n");
        fwrite($fh, "colnames(coords)[2] <- \"Latitude\";\n");
        fwrite($fh, "sink(\"dataMap.js\");\n");
        fwrite($fh, "cat(\"var freqData=[\\n\");\n");
        fwrite($fh, "for (i in (1:dim(new_x)[1])){  \n");
        fwrite($fh, "if(!is.na(coords[rownames(new_x)[i],1]) && !is.na(coords[rownames(new_x)[i],2])) {\n");
        fwrite($fh, "  cat(paste(\"{Samples:\'\",rownames(new_x)[i],\"\',\",sep=\"\"));\n");
        fwrite($fh, "  cat(paste(\"freq:{\",paste(paste(names,\":\",new_x[i,],sep=\"\"),collapse=\",\"),\"},\",sep=\"\"));\n");
        fwrite($fh, "  cat(paste(\"MDS:{\",paste(paste(colnames(coords),coords[rownames(new_x)[i],],sep=\":\"),collapse=\",\"),\"}}\\n\",sep=\"\"));\n");
        fwrite($fh, "  if(i!=dim(new_x)[1]){cat(\",\")};\n");
        fwrite($fh, "};\n");
        fwrite($fh, "};\n");
        fwrite($fh, "cat(\"];\\n\\n\");\n");

        fwrite($fh, "sink();\n");

        fclose($fh);
        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))
                exit("Unable to open file: $job_folder/$job_id.pbs");
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to mapping_tool_div_visual function.
     *
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @param string $params A string designated to contain the input parameterers used by this job
     * @return boolean
     */
    protected function mapping_tools_div_visual($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){
        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        if(empty($form['box3'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        $box= $form['box'];
        $box2 = $form['box2'];
        $box3 = $form['box3'];
        $inputs .= ";".$box2;
        $inputs .= ";".$box3;


        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }

        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }

        if(empty($form['top_species'])){
            Session::flash('toastr',array('error','You forgot to set the number of top ranked species!'));
            return false;
        } else {
            $top_species = $form['top_species'];
        }


        if(!empty($form['column_select'])){
            $column_select = $form['column_select'];
        } else {
            Session::flash('toastr',array('error','You forgot to set the column in the factor file!'));
            return false;
        }


        $params .= ";transpose:".$transpose;
        $params .= ";transformation method:".$transformation_method;
        $params .= ";top species:".$top_species;
        $params .= ";Indices column:".$column_select;

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        $workspace_filepath = $user_workspace.'/'.$box3;
        $job_filepath = $job_folder.'/'.$box3;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        $image_filepath = public_path().'/images/world_3.kml';
        $job_filepath = $job_folder.'/world_3.kml';
        if(!copy($image_filepath,$job_filepath)){
            $this->log_event('Moving image from public to job folder, failed.',"error");
            throw new Exception();
        }

        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))
                exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "library(stringr);\n");
        fwrite($fh, "library(maptools);\n");
        fwrite($fh, "library(sp);\n");
        fwrite($fh, "library(plyr);\n");
        fwrite($fh, "library(dplyr);\n");
        fwrite($fh, "library(tidyr);\n");

        fwrite($fh, "x <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\",row.names=1);\n");
        fwrite($fh, "coords <- read.table(\"$remote_job_folder/$box2\",header = TRUE, sep=\",\",row.names=1);\n");
        fwrite($fh, "colnames(coords) <- gsub(\"\\\.\",\"_\",gsub(\" \",\"_\",colnames(coords)));\n");
        fwrite($fh, "colnames(coords)[1] <- \"Longitude\";\n");
        fwrite($fh, "colnames(coords)[2] <- \"Latitude\";\n");
        fwrite($fh, "indices <- read.table(\"$remote_job_folder/$box3\",header = TRUE, sep=\",\",row.names=1);\n");
        if($transpose == "transpose"){
            fwrite($fh, "x <- t(x);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "x <- decostand(x, method = \"$transformation_method\");\n");
        }

        fwrite($fh, "tkml <- getKMLcoordinates(kmlfile=\"world_3.kml\", ignoreAltitude=T);\n");



        fwrite($fh, "#Create polygon from coordinates;\n");
        fwrite($fh, "p1<-list();\n");
        fwrite($fh, "for (i in 1:length(tkml))  p1[[i]] <- Polygon(tkml[[i]])   #loop nodig\n");

        fwrite($fh, "#Create Polygon class;\n");
        fwrite($fh, "p2 = Polygons(p1, ID=\"z\");\n");

        fwrite($fh, "#Create Spatial Polygons class en referentie systeem is nodig;\n");
        fwrite($fh, "p3= SpatialPolygons(list(p2),proj4string=CRS(\"+proj=longlat +datum=WGS84 +ellps=WGS84 +towgs84=0,0,0\"));\n");

        fwrite($fh, "polys<-list(\"sp.polygons\", p3, fill = \"lightgreen\");\n");

        fwrite($fh, "sub_points<-coords%>%\n");
        fwrite($fh, "select(Longitude,Latitude);\n");


        fwrite($fh, "sub_points_coords<-sub_points[,1:2];\n");

        fwrite($fh, "sub_points_SP<-SpatialPoints(sub_points_coords);\n");


        fwrite($fh, "sub_points_SPDF<-SpatialPointsDataFrame(sub_points_coords, indices);\n");

        fwrite($fh, "#Set color set to be used for classes of data  ;\n");
        fwrite($fh, "colorset6<-c(\"#FFFF00\", \"#FFCC00\", \"#FF9900\", \"#FF6600\", \"#FF3300\", \"#FF0000\");\n");



        fwrite($fh, "plottest <- spplot(sub_points_SPDF, zcol=c(\"$column_select\"), xlab=\"\",\n");
        fwrite($fh, "scales=list(draw = TRUE), sp.layout=list(polys), cuts = 6, col.regions=colorset6,xlim=c(-180,180),ylim=c(-80,80),par.settings = list(panel.background=list(col=\"lightblue\")));\n");


        fwrite($fh, "x<-x/rowSums(x);\n");
        fwrite($fh, "x<-x[,order(colSums(x),decreasing=TRUE)];\n");
        fwrite($fh, "#Extract list of top N Taxa;\n");
        fwrite($fh, "N<-$top_species;\n");
        fwrite($fh, "taxa_list<-colnames(x)[1:N];\n");
        fwrite($fh, "#remove \"__Unknown__\" and add it to others;\n");
        fwrite($fh, "taxa_list<-taxa_list[!grepl(\"__Unknown__\",taxa_list)];\n");
        fwrite($fh, "N<-length(taxa_list);\n");
        fwrite($fh, "new_x<-data.frame(x[,colnames(x) %in% taxa_list],Others=rowSums(x[,!colnames(x) %in% taxa_list]));\n");
        fwrite($fh, "names<-gsub(\"\\\.\",\"_\",gsub(\" \",\"_\",colnames(new_x)));\n");
        fwrite($fh, "rownames(new_x) <- gsub(\"\\\.\",\"-\",gsub(\" \",\"_\",rownames(new_x)));\n");
        fwrite($fh, "sink(\"dataMapDiv.js\");\n");
        fwrite($fh, "cat(\"var freqData=[\\n\");\n");
        fwrite($fh, "for (i in (1:dim(new_x)[1])){  \n");
        fwrite($fh, "if(!is.na(coords[rownames(new_x)[i],1]) && !is.na(coords[rownames(new_x)[i],2])) {\n");
        fwrite($fh, "  cat(paste(\"{Samples:\'\",rownames(new_x)[i],\"\',\",sep=\"\"));\n");
        fwrite($fh, "  cat(paste(\"freq:{\",paste(paste(names,\":\",new_x[i,],sep=\"\"),collapse=\",\"),\"},\",sep=\"\"));\n");
        fwrite($fh, "  cat(paste(\"MDS:{\",paste(paste(colnames(coords),coords[rownames(new_x)[i],],sep=\":\"),collapse=\",\"),\"}}\\n\",sep=\"\"));\n");
        fwrite($fh, "  if(i!=dim(new_x)[1]){cat(\",\")};\n");
        fwrite($fh, "};\n");
        fwrite($fh, "};\n");
        fwrite($fh, "cat(\"];\\n\\n\");\n");

        fwrite($fh, "cat(\"var legendDiv=[\\n\");\n");
        fwrite($fh, "labels <- as.data.frame(c(\"Up\",\"Down\"));\n");

        fwrite($fh, "rownames(labels) <- labels[,1];\n");
        fwrite($fh, "for (i in (1:length(plottest\$legend\$bottom\$args\$key\$text[[1]]))){  \n");
        fwrite($fh, "legend<-gsub(\"\\\[\",\"\",gsub(\"\\\(\",\"\",gsub(\"\\\]\",\"\",gsub(\"\\\)\",\"\",plottest\$legend\$bottom\$args\$key\$text[[1]][i]))));\n");
        fwrite($fh, "legend2 <- as.data.frame(strsplit(legend, \",\"));\n");
        fwrite($fh, "rownames(legend2) <- labels[,1];\n");
        fwrite($fh, "legend2<- t(legend2);\n");

        fwrite($fh, "cat(paste(\"{fact:{\",paste(paste(rownames(labels),legend2,sep=\":\"),collapse=\",\"),\"}}\\n\",sep=\"\") );\n");
        fwrite($fh, "if(i!=length(plottest\$legend\$bottom\$args\$key\$text[[1]])){cat(\",\")};\n");
        fwrite($fh, "};\n");
        fwrite($fh, "cat(\"];\\n\\n\");\n");

        fwrite($fh, "cat(\"var indices=[\\n\");\n");
        fwrite($fh, "for (i in (1:length(sub_points_SPDF\$$column_select))){  \n");
        fwrite($fh, "if(!is.na(coords[rownames(new_x)[i],1]) && !is.na(coords[rownames(new_x)[i],2])) {\n");
        fwrite($fh, "cat(paste(\"{fact:\",paste(paste(sub_points_SPDF\$".$column_select."[i],sep=\":\"),collapse=\",\"),\"}\\n\",sep=\"\"))     ;\n");

        fwrite($fh, "if(i!=length(sub_points_SPDF\$$column_select)[1]){cat(\",\")};\n");
        fwrite($fh, "};\n");
        fwrite($fh, "};\n");
        fwrite($fh, "cat(\"];\\n\");\n");
        fwrite($fh, "cat(\"var indiceslabel=[\\n\");\n");

        fwrite($fh, "cat(paste(\"{fact:\\\"\",paste(paste(\"$column_select\",sep=\":\"),collapse=\",\"),\"\\\"}\\n\",sep=\"\")) ;\n");
        fwrite($fh, "cat(\"];\n\");\n");

        fwrite($fh, "sink();\n");

        fclose($fh);
        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))
                exit("Unable to open file: $job_folder/$job_id.pbs");
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to metamds function.
     *
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @param string $params A string designated to contain the input parameterers used by this job
     * @return boolean
     */
    protected function metamds($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        $box= $form['box'];

        if(!empty($form['column_select'])){
            $column_select = $form['column_select'];
            $params .= ";factor column:".$column_select;
        } else {
            if(!empty($form['box2'])){
                Session::flash('toastr',array('error','You forgot to select column!'));
                return false;
            } else {
                $params .= ";factor column: ";
            }
        }

        if(empty($form['transpose'])){
            $transpose = "";
            $params .= ";transpose: ";
        } else {
            $transpose = $form['transpose'];
            $params .= ";transpose:".$transpose;
        }

        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
            $params .= ";transf_method_select:".$transformation_method;
        }

        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the Method parameter!'));
            return false;
        } else {
            $method_select = $form['method_select'];
            $params .= ";method_select:".$method_select;
        }

        if(empty($form['k_select'])){
            Session::flash('toastr',array('error','You forgot to set the K parameter!'));
            return false;
        } else {
            $K = $form['k_select'];
            $params .= ";k_select:".$K;
        }

        if(empty($form['trymax'])){
            Session::flash('toastr',array('error','You forgot to set the trymax parameter!'));
            return false;
        } else {
            $trymax = $form['trymax'];
            $params .= ";trymax:".$trymax;
        }

        if(empty($form['autotransform_select'])){
            Session::flash('toastr',array('error','You forgot to set the autotransform parameter!'));
            return false;
        } else {
            $autotransform_select = $form['autotransform_select'];
            $params .= ";autotransform_select:".$autotransform_select;
        }

        if(empty($form['noshare'])){
            Session::flash('toastr',array('error','You forgot to set the noshare parameter!'));
            return false;
        } else {
            $noshare = $form['noshare'];
            $params .= ";noshare:".$noshare;
        }

        if(empty($form['wascores_select'])){
            Session::flash('toastr',array('error','You forgot to set the wascores_select parameter!'));
            return false;
        } else {
            $wascores_select = $form['wascores_select'];
            $params .= ";wascores_select:".$wascores_select;
        }

        if(empty($form['expand'])){
            Session::flash('toastr',array('error','You forgot to set the expand parameter!'));
            return false;
        } else {
            $expand = $form['expand'];
            $params .= ";expand:".$expand;
        }

        if(empty($form['trace'])){
            Session::flash('toastr',array('error','You forgot to set the trace parameter!'));
            return false;
        } else {
            $trace = $form['trace'];
            $params .= ";trace:".$trace;
        }

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        if(!empty($form['box2'])){
            $box2 = $form['box2'];
            $inputs .= ";".$box2;
            $workspace_filepath = $user_workspace.'/'.$box2;
            $job_filepath = $job_folder.'/'.$box2;
            if(!copy($workspace_filepath,$job_filepath)){
                $this->log_event('Moving file from workspace to job folder, failed.',"error");
                throw new Exception();
            }
        }

        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))
                exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\",row.names=1);\n");

        if($transpose == "transpose"){
            fwrite($fh, "mat <- t(mat);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }

        if(empty($form['box2'])){
            fwrite($fh, "labels <- as.factor(rownames(mat));\n");
            fwrite($fh, "n<- length(labels);\n");
            fwrite($fh, "rain <- rainbow(n, s = 1, v = 1, start = 0, end = max(1, n - 1)/n, alpha = 0.8);\n");
            fwrite($fh, "labels <- rain;\n");
        }else{
            fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box2\", header = TRUE, sep=\",\" ,row.names=1);\n");
            fwrite($fh, "labels <- as.factor(ENV\$$column_select);\n");
        }

        fwrite($fh, "otu.nmds <- metaMDS(mat,distance=\"$method_select\");\n");//,k = $K, trymax = $trymax, autotransform =$autotransform_select,noshare = $noshare, wascores = $wascores_select, expand = $expand, trace = $trace);\n");
        fwrite($fh, "par(xpd=TRUE);\n");
        fwrite($fh, "png('legend.png',height = 700,width=350)\n");
        fwrite($fh, "plot(otu.nmds, type = \"n\",ylab=\"\",xlab=\"\",yaxt=\"n\",xaxt=\"n\",bty=\"n\")\n");
        if(empty($form['box2'])){
            fwrite($fh, "legend(\"topright\", legend=rownames(mat), col=labels, pch = 16);\n");
        }else{
            fwrite($fh, "legend(\"topright\", legend=unique(ENV\$$column_select), col=unique(labels), pch = 16);\n");
        }
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "png('rplot.png',height = 600,width=600)\n");
        fwrite($fh, "plot(otu.nmds, type = \"n\")\n");
        fwrite($fh, "points(otu.nmds, col = labels, pch = 16,cex = 1.7);\n");//
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "otu.nmds;\n");
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))
                exit("Unable to open file: $job_folder/$job_id.pbs");

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to metamds_visual function.
     *
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @param string $params A string designated to contain the input parameterers used by this job
     * @return boolean
     */
    protected function metamds_visual($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        $box= $form['box'];

        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }

        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }

        if(empty($form['method_select_viz'])){
            Session::flash('toastr',array('error','You forgot to set the Method parameter!'));
            return false;
        } else {
            $method_select = $form['method_select_viz'];
        }

        if(empty($form['k_select_viz'])){
            Session::flash('toastr',array('error','You forgot to set the K parameter!'));
            return false;
        } else {
            $K = $form['k_select_viz'];
        }

        if(empty($form['trymax_viz'])){
            Session::flash('toastr',array('error','You forgot to set the trymax parameter!'));
            return false;
        } else {
            $trymax = $form['trymax_viz'];
        }

        if(empty($form['top_species'])){
            Session::flash('toastr',array('error','You forgot to set the number of top ranked species!'));
            return false;
        } else {
            $top_species = $form['top_species'];
        }

        $params .= ";transpose:".$transpose;
        $params .= ";transf_method_select:".$transformation_method;
        $params .= ";method_select_viz:".$method_select;
        $params .= ";k_select_viz:".$K;
        $params .= ";trymax_viz:".$trymax;
        $params .= ";top_species:".$top_species;

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        // Move a required file
        $script_source = app_path().'/rvlab/files/summarize.html';
        copy($script_source,"$job_folder/summarize.html");

        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))
                exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "x <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\",row.names=1);\n");

        if($transpose == "transpose"){
            fwrite($fh, "x <- t(x);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "x <- decostand(x, method = \"$transformation_method\");\n");
        }

        fwrite($fh, "MDS<-metaMDS(x, distance = \"$method_select\", k = $K, trymax = $trymax);\n");
        fwrite($fh, "x<-x/rowSums(x);\n");
        fwrite($fh, "x<-x[,order(colSums(x),decreasing=TRUE)];\n");
        fwrite($fh, "#Extract list of top N Taxa;\n");
        fwrite($fh, "N<-$top_species;\n");
        fwrite($fh, "taxa_list<-colnames(x)[1:N];\n");
        fwrite($fh, "#remove \"__Unknown__\" and add it to others;\n");
        fwrite($fh, "taxa_list<-taxa_list[!grepl(\"__Unknown__\",taxa_list)];\n");
        fwrite($fh, "N<-length(taxa_list);\n");
        fwrite($fh, "new_x<-data.frame(x[,colnames(x) %in% taxa_list],Others=rowSums(x[,!colnames(x) %in% taxa_list]));\n");
        fwrite($fh, "new_x2 <- t(new_x);\n");
        fwrite($fh, "write.table(new_x2, file = \"$remote_job_folder/filtered_abundance.csv\",sep=\",\",quote = FALSE,row.names = TRUE,col.names=NA);\n");
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
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))
                exit("Unable to open file: $job_folder/$job_id.pbs");
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to phylobar function.
     *
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @param string $params A string designated to contain the input parameterers used by this job
     * @return boolean
     */
    protected function phylobar($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        $box= $form['box'];
        $box2= $form['box2'];
        $inputs .= ";".$box2;



        if(empty($form['top_nodes'])){
            Session::flash('toastr',array('error','You forgot to set the number of nodes!'));
            return false;
        } else {
            $top_nodes = $form['top_nodes'];
        }


        $params .= ";top nodes:".$top_nodes;

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/table.nwk';
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/table.csv';
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        // Move a required file
        //$script_source = app_path().'/rvlab/files/HEATcloud.html';
        //copy($script_source,"$job_folder/HEATcloud.html");


        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to pca function.
     *
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @param string $params A string designated to contain the input parameterers used by this job
     * @return boolean
     */
    protected function pca($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        $box= $form['box'];

        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }

        if(!empty($form['transpose'])){
            $transpose = $form['transpose'];
        } else {
            $transpose = "";
        }

        if(!empty($form['box2'])){
            $box2 = $form['box2'];
            if(!empty($form['column_select'])){
                $column_select = $form['column_select'];
                $params .= ";factor column:".$column_select;
            } else {
                Session::flash('toastr',array('error','You forgot to set the column in the factor file!'));
                return false;
            }
            $inputs .= ";".$box2;
        } else {
            $box2 = "";
            $params .= ";factor column: ";
        }

        $params .= ";transformation method:".$transformation_method;
        $params .= ";transpose:".$transpose;

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        if(!empty($box2)){
            $workspace_filepath = $user_workspace.'/'.$box2;
            $job_filepath = $job_folder.'/'.$box2;
            if(!copy($workspace_filepath,$job_filepath)){
                $this->log_event('Moving file from workspace to job folder, failed.',"error");
                throw new Exception();
            }
        }

        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))
                exit("Unable to open file $job_folder/$job_id.R");
        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\",row.names=1);\n");

        if($transpose == "transpose"){
            fwrite($fh, "mat <- t(mat);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }

        if(empty($box2)){
            fwrite($fh, "labels <- as.factor(rownames(mat));\n");
            fwrite($fh, "n<- length(labels);\n");
            fwrite($fh, "rain <- rainbow(n, s = 1, v = 1, start = 0, end = max(1, n - 1)/n, alpha = 0.8);\n");
            fwrite($fh, "labels <- rain;\n");
        }else{
            fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box2\", header = TRUE, sep=\",\" ,row.names=1);\n");
            fwrite($fh, "labels <- as.factor(ENV\$$column_select);\n");
        }

        fwrite($fh, "otu.pca <- rda(mat);\n");
        fwrite($fh, "par(xpd=TRUE);\n");
        fwrite($fh, "png('$remote_job_folder/legend.png',height = 700,width=350)\n");
        fwrite($fh, "plot(otu.pca, type = \"n\",ylab=\"\",xlab=\"\",yaxt=\"n\",xaxt=\"n\",bty=\"n\")\n");
        fwrite($fh, "abline(h=0,col=\"white\",lty=1,lwd=3);\n");
        fwrite($fh, "abline(v=0,col=\"white\",lty=1,lwd=3);\n");
        if(empty($box2)){
            fwrite($fh, "legend(\"topright\", legend=rownames(mat), col=labels, pch = 16);\n");
        }else{
            fwrite($fh, "legend(\"topright\", legend=unique(ENV\$$column_select), col=unique(labels), pch = 16);\n");
        }

        fwrite($fh, "dev.off()\n");
        fwrite($fh, "png('$remote_job_folder/rplot.png',height = 600,width=600)\n");
        fwrite($fh, "plot(otu.pca, type = \"n\")\n");
        fwrite($fh, "points(otu.pca, col = labels, pch = 16,cex = 1.7);\n");
        fwrite($fh, "dev.off()\n");

        fwrite($fh, "pdf(file='$remote_job_folder/rplot.pdf',width=10, height=10)\n");
        fwrite($fh, "plot(otu.pca, type = \"n\")\n");
        fwrite($fh, "points(otu.pca, col = labels, pch = 16,cex = 1.7);\n");
        fwrite($fh, "plot(otu.pca, type = \"n\",ylab=\"\",xlab=\"\",yaxt=\"n\",xaxt=\"n\",bty=\"n\")\n");
        fwrite($fh, "abline(h=0,col=\"white\",lty=1,lwd=3);\n");
        fwrite($fh, "abline(v=0,col=\"white\",lty=1,lwd=3);\n");
        if(empty($box2)){
            fwrite($fh, "legend(\"topright\", legend=rownames(mat), col=labels, pch = 16);\n");
        }else{
            fwrite($fh, "legend(\"topright\", legend=unique(ENV\$$column_select), col=unique(labels), pch = 16);\n");
        }
        fwrite($fh, "dev.off()\n");

        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "otu.pca;\n");
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))
                exit("Unable to open file: $job_folder/$job_id.pbs");
        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;

    }

    /**
     * Handles the part of job submission functionlity that relates to regression function.
     *
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @param string $params A string designated to contain the input parameterers used by this job
     * @return boolean
     */
    protected function regression($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        $box= $form['box'];

        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }

        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }

        if(empty($form['single_or_multi'])){
            Session::flash('toastr',array('error','You forgot to set the regression type (single or multi)!'));
            return false;
        } else {
            $single_or_multi = $form['single_or_multi'];
        }

        if(empty($form['Factor_select1'])){
            Session::flash('toastr',array('error','You forgot to set Factor 1!'));
            return false;
        } else {
            $Factor1 = $form['Factor_select1'];
        }

        if(empty($form['Factor_select2'])){
            Session::flash('toastr',array('error','You forgot to set Factor 2!'));
            return false;
        } else {
            $Factor2 = $form['Factor_select2'];
        }

        if(empty($form['Factor_select3'])){
            $Factor3 = "";
        } else {
            $Factor3 = $form['Factor_select3'];
        }

        $params .= ";transpose:".$transpose;
        $params .= ";transformation method:".$transformation_method;
        $params .= ";regression formula:".$single_or_multi;
        $params .= ";factor column 1:".$Factor1;
        $params .= ";factor column 2:".$Factor2;
        $params .= ";factor column 3:".$Factor3;

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))
                exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "library(stats);\n");
        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "fact <- read.table(\"$remote_job_folder/$box\", row.names=1, header = TRUE, sep=\",\");\n");


        if($transformation_method != "none"){
            fwrite($fh, "fact <- decostand(fact, method = \"$transformation_method\");\n");
        }

        fwrite($fh, "attach(fact);\n");
        if($single_or_multi =="single"){
            fwrite($fh, "fit<-lm($Factor1~$Factor2);\n");
            fwrite($fh, "png('rplot.png')\n");
            fwrite($fh, "plot($Factor1~$Factor2)\n");//, xlim = c(3, 5), ylim = c(4, 10))\n");
            fwrite($fh, "abline(fit, col=\"red\")\n");
            fwrite($fh, "dev.off()\n");

        }else{
            fwrite($fh, "fit<-lm($Factor1~$Factor2+$Factor3);\n");
            fwrite($fh, "png('rplot.png')\n");
            fwrite($fh, "plot($Factor1~$Factor2+$Factor3)\n");//, xlim = c(3, 5), ylim = c(4, 10))\n");
            fwrite($fh, "abline(fit, col=\"red\")\n");
            fwrite($fh, "dev.off()\n");
        }
        fwrite($fh, "png('rplot2.png')\n");
        fwrite($fh, "layout(matrix(c(1,2,3,4),2,2))\n");
        fwrite($fh, "plot(fit)\n");//, xlim = c(3, 5), ylim = c(4, 10))\n");
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "summary(fit);\n");
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))
                exit("Unable to open file: $job_folder/$job_id.pbs");

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to second_metamds function.
     *
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @param string $params A string designated to contain the input parameterers used by this job
     * @return boolean
     */
    protected function second_metamds($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        $box = $form['box'];

        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }

        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }

        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the Method parameter!'));
            return false;
        } else {
            $method_select = $form['method_select'];
        }

        if(empty($form['cor_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the Cor. Coeff parameter!'));
            return false;
        } else {
            $cor_method_select = $form['cor_method_select'];
        }

        if(empty($form['k_select'])){
            Session::flash('toastr',array('error','You forgot to set the K parameter!'));
            return false;
        } else {
            $K = $form['k_select'];
        }

        if(empty($form['trymax'])){
            Session::flash('toastr',array('error','You forgot to set the trymax parameter!'));
            return false;
        } else {
            $trymax = $form['trymax'];
        }

        if(empty($form['autotransform_select'])){
            Session::flash('toastr',array('error','You forgot to set the autotransform parameter!'));
            return false;
        } else {
            $autotransform_select = $form['autotransform_select'];
        }

        if(empty($form['noshare'])){
            Session::flash('toastr',array('error','You forgot to set the noshare parameter!'));
            return false;
        } else {
            $noshare = $form['noshare'];
        }

        if(empty($form['wascores_select'])){
            Session::flash('toastr',array('error','You forgot to set the wascores_select parameter!'));
            return false;
        } else {
            $wascores_select = $form['wascores_select'];
        }

        if(empty($form['expand'])){
            Session::flash('toastr',array('error','You forgot to set the expand parameter!'));
            return false;
        } else {
            $expand = $form['expand'];
        }

        if(empty($form['trace'])){
            Session::flash('toastr',array('error','You forgot to set the trace parameter!'));
            return false;
        } else {
            $trace = $form['trace'];
        }

        $params .= ";transpose:".$transpose;
        $params .= ";transformation method:".$transformation_method;
        $params .= ";method:".$method_select;
        $params .= ";Cor. Coeff.:".$cor_method_select;
        $params .= ";K:".$K;
        $params .= ";trymax:".$trymax;
        $params .= ";autotransform:".$autotransform_select;
        $params .= ";noshare:".$noshare;
        $params .= ";wascores:".$wascores_select;
        $params .= ";expand:".$expand;
        $params .= ";trace:".$trace;

        // Move input file from workspace to job's folder
        foreach($box as $box_file){
            $workspace_filepath = $user_workspace.'/'.$box_file;
            $job_filepath = $job_folder.'/'.$box_file;
            if(!copy($workspace_filepath,$job_filepath)){
                $this->log_event('Moving file from workspace to job folder, failed.',"error");
                throw new Exception();
            }
        }

        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))
            exit("Unable to open file $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "library(ecodist);\n");
        $filecount = 1;
        fwrite($fh, "# replace missing data with 0;\n");
        fwrite($fh, "# fourth root or any other transformation here, excluding first column with taxon names;\n");
        fwrite($fh, "#transpose the matrices, bcdist needs rows as samples;\n");
        fwrite($fh, "# calculate bray curtis for all;\n");
        foreach ($box as $val) {

            fwrite($fh, "mat".$filecount." <- read.table(\"$remote_job_folder/$val\", header = TRUE, sep=\",\",row.names=1);\n");
            if($transpose == "transpose"){
                fwrite($fh, "mat".$filecount." <- t(mat".$filecount.");\n");
            }

            if($transformation_method != "none"){
                fwrite($fh, "mat".$filecount." <- decostand(mat".$filecount.", method = \"$transformation_method\");\n");
            }

            fwrite($fh, "mat".$filecount."[is.na(mat".$filecount.")]<-0;\n");
            fwrite($fh, "mat".$filecount."_2 <- sqrt(sqrt(mat".$filecount."));\n");//[,-1]
            fwrite($fh, "mat".$filecount."_tr <- t(mat".$filecount."_2);\n");
            fwrite($fh, "bc".$filecount." <-bcdist(mat".$filecount."_tr);\n");
            $filecount++;

        }
        fwrite($fh, "#create an empty matrix to fill in the correlation coefficients;\n");
        $filecount = $filecount-1;
        fwrite($fh, "bcs <- matrix(NA, ncol=".$filecount.", nrow=".$filecount.");\n");
        fwrite($fh,"combs <- combn(1:$filecount, 2);\n");
        fwrite($fh,"for (i in 1:ncol(combs) ) {\n");
        fwrite($fh, "bc1_t <- paste(\"bc\",combs[1,i],sep=\"\");\n");
        fwrite($fh, "bc2_t <- paste(\"bc\",combs[2,i],sep=\"\");\n");
        fwrite($fh, "bcs[combs[1,i],combs[2,i]] <- cor(get(bc1_t), get(bc2_t), method=\"$cor_method_select\");\n");
        fwrite($fh,"}\n");
        fwrite($fh,"bcs <- t(bcs)\n");
        fwrite($fh, "x <- c(\"$box[0]\");\n");
        for ($j=1; $j<sizeof($box); $j++) {
            fwrite($fh, "x <- append(x, \"$box[$j]\");\n");
        }
        fwrite($fh, "colnames(bcs) <-x;\n");
        fwrite($fh, "rownames(bcs) <-x;\n");

        fwrite($fh, "#transform the matrix into a dissimlarity matrix of format \"dis\";\n");
        fwrite($fh, "dist1 <- as.dist(bcs, diag = FALSE, upper = FALSE);\n");

        fwrite($fh, "#dist2 <- as.dist(NA);\n");
        fwrite($fh, "dist2<- 1-dist1;\n");

        fwrite($fh, "#run the mds;\n");
        fwrite($fh, "mydata.mds<- metaMDS(dist2,  k = $K, trymax = $trymax,distance=\"$method_select\");\n");
        fwrite($fh, "save(dist2, ascii=TRUE, file = \"$remote_job_folder/dist_2nd_stage.csv\");\n");

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
        fwrite($fh, "points(mydata.mds, display = c(\"sites\", \"species\"), cex = 1.8, pch=19, col=labels);\n");#0.8
        fwrite($fh, "# add the labels for the stations;\n");
        fwrite($fh, "text(mydata.mds, display = c(\"sites\", \"species\"), cex = 1.0 , pos=3 );\n");#0.7, 3
        fwrite($fh, "dev.off()\n");


        fwrite($fh, "#alternative plotting:;\n");
        fwrite($fh, "#ordipointlabel(mydata.mds, display =\"spec\");\n");
        fwrite($fh, "#points(mydata.mds, display = \"spec\", cex = 1.0, pch=20, col=\"red\", type=\"t\"');\n");

        fwrite($fh, "#alternative plotting - allows to drag the labels to a better position and then export the graphic as EPS;\n");
        fwrite($fh, "#orditkplot(mydata.mds) ;\n");
        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "mydata.mds;\n");
        fclose($fh);

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))
                exit("Unable to open file: $job_folder/$job_id.pbs");

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to taxondive function.
     *
     * @param array $form Contains the fields of the submitted function configuration form
     * @param int $job_id The id of the newly created job
     * @param string $job_folder The local (web server) path to the job folder
     * @param string $remote_job_folder The remote (cluster) path to the job folder
     * @param string $user_workspace The local (web server) path to user's input files
     * @param string $remote_user_workspace The remote (cluster) path to user's input files
     * @param string $inputs A string designated to contain the names of the input files to be used by this job
     * @param string $params A string designated to contain the input parameterers used by this job
     * @return boolean
     */
    protected function taxondive($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        if(empty($form['deltalamda'])){
            Session::flash('toastr',array('error','You forgot to select delta or lamda parameter!'));
            return false;
        } else {
            $deltalamda = $form['deltalamda'];
            $params .= ";deltalamda:".$deltalamda;
        }

        $box= $form['box'];
        $box2 = $form['box2'];
        $inputs .= ";".$box2;

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        $workspace_filepath = $user_workspace.'/'.$box2;
        $job_filepath = $job_folder.'/'.$box2;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        if(!empty($form['box3'])){
            $box3 = $form['box3'];
            $workspace_filepath = $user_workspace.'/'.$box3;
            $job_filepath = $job_folder.'/'.$box3;
            if(!copy($workspace_filepath,$job_filepath)){
                $this->log_event('Moving file from workspace to job folder, failed.',"error");
                throw new Exception();
            }
            $inputs .= ";".$box3;
        }

        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))
                exit("Unable to open file: $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "taxdis <- get(load(\"$remote_job_folder/$box2\"));\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\" ,row.names=1);\n");

        if(!empty($form['transpose'])){
            fwrite($fh, "mat <- t(mat);\n");
            $params .= ";transpose:".$form['transpose'];
        } else {
            $params .= ";transpose: ";
        }

        $transformation_method = $form['transf_method_select'];
        $params .= ";transf_method_select:".$transformation_method;
        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }

        $match_force = $form['match_force'];
        $params .= ";match_force:".$match_force;

        fwrite($fh, "taxondive <- taxondive(mat,taxdis,match.force=$match_force);\n");
        fwrite($fh, "save(taxondive, ascii=TRUE, file = \"$remote_job_folder/taxondive.csv\");\n");

        if (!empty($form['column_select'])) {
            $params .= ";column_select:".$form['column_select'];
        } else {
            $params .= ";column_select: ";
        }


        if(empty($box3)){
            fwrite($fh, "labels <- as.factor(rownames(mat));\n");
            fwrite($fh, "n<- length(labels);\n");
            fwrite($fh, "rain <- rainbow(n, s = 1, v = 1, start = 0, end = max(1, n - 1)/n, alpha = 0.8);\n");
            fwrite($fh, "labels <- rain;\n");
        }else{
            $column_select = $form['column_select'];
            fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box3\", header = TRUE, sep=\",\" ,row.names=1);\n");
            fwrite($fh, "labels <- as.factor(ENV\$$column_select);\n");
        }
        fwrite($fh, "png('legend.png',height = 700, width = 350)\n");
        fwrite($fh, "plot(mat, type = \"n\",ylab=\"\",xlab=\"\",yaxt=\"n\",xaxt=\"n\",bty=\"n\")\n");
        if(empty($box3)){
            fwrite($fh, "legend(\"topright\", legend=rownames(mat), col=labels, pch = 16);\n");
        }else{
            fwrite($fh, "legend(\"topright\", legend=unique(ENV\$$column_select), col=unique(labels), pch = 16);\n");
        }
        fwrite($fh, "dev.off()\n");
        fwrite($fh, "png('rplot.png',height = 600, width = 600)\n");
        if($deltalamda=="Delta"){
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

        // Build the bash script
        if (!($fh2 = fopen($job_folder."/$job_id.pbs", "w")))
                exit("Unable to open file: $job_folder/$job_id.pbs");

        fwrite($fh2, "#!/bin/bash\n");
        fwrite($fh2, "#PBS -l walltime=02:00:00\n"); // Maximum execution time is 2 hours
        fwrite($fh2, "#PBS -N $job_id\n");
        fwrite($fh2, "#PBS -d $remote_job_folder\n"); // Bash script output goes to <job_id>.log. Errors will be logged in this file.
        fwrite($fh2, "#PBS -o $job_id.log\n");    // The log file will be moved to the job folder after the end of the R script execution
        fwrite($fh2, "#PBS -j oe\n");
        fwrite($fh2, "#PBS -m n\n");
        fwrite($fh2, "#PBS -l nodes=1:ppn=1\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "/usr/bin/R CMD BATCH $remote_job_folder/$job_id.R > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

}