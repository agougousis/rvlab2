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
class SerialController extends JobController {

    public function __construct() {
        parent::__construct();
    }

    /**
     * Handles the part of job submission functionlity that relates to bict function.
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
    protected function bict($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        if(empty($form['species_family_select'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        $box= $form['box'];
        $sp_fam = $form['species_family_select'];
        $params .= ";species or family:".$sp_fam;

        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        if(!empty($form['box2'])){
            $box2= $form['box2'];
            $inputs .= ";".$box2;

            $workspace_filepath = $user_workspace.'/'.$box2;
            $job_filepath = $job_folder.'/'.$box2;
            if(!copy($workspace_filepath,$job_filepath)){
                $this->log_event('Moving file from workspace to job folder, failed.',"error");
                throw new Exception();
            }
        }

        // Move required files
        $script_source = app_path().'/rvlab/files/indices';
        copy($script_source,"$job_folder/indices");

        $bqi = app_path().'/rvlab/files/bqi.csv';
        copy($bqi,"$job_folder/bqi.csv");

        $ambi = app_path().'/rvlab/files/ambi.csv';
        copy($ambi,"$job_folder/ambi.csv");

        $bentix = app_path().'/rvlab/files/bentix.csv';
        copy($bentix,"$job_folder/bentix.csv");

        $bqif = app_path().'/rvlab/files/bqi.family.csv';
        copy($bqif,"$job_folder/bqi.family.csv");

        $distinct = app_path().'/rvlab/files/TaxDistinctness.R';
        copy($distinct,"$job_folder/TaxDistinctness.R");

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

        if($sp_fam == 'species'){
            if(empty($box2)){
                fwrite($fh2, "tr '\r' '\n' < $remote_job_folder/$box >$remote_job_folder/tmp.csv\n");
                fwrite($fh2, "$remote_job_folder/indices -i$remote_job_folder/tmp.csv -o$remote_job_folder/indices.txt -B/dev/null -X/dev/null -A/dev/null > $remote_job_folder/cmd_line_output.txt \n");
            } else {
                fwrite($fh2, "tr '\r' '\n' < $remote_job_folder/$box >$remote_job_folder/tmp.csv\n");
                fwrite($fh2, "tr '\r' '\n' < $remote_job_folder/$box2 > $remote_job_folder/tmp2.csv\n");
                fwrite($fh2, "$remote_job_folder/indices -i$remote_job_folder/tmp.csv -d$remote_job_folder/tmp2.csv -o$remote_job_folder/indices.txt -B/dev/null -X/dev/null -A/dev/null > $remote_job_folder/cmd_line_output.txt\n");
            }
        } else {
            fwrite($fh2, "tr '\r' '\n' < $remote_job_folder/$box >$remote_job_folder/tmp.csv\n");
            fwrite($fh2, "$remote_job_folder/indices -i$remote_job_folder/tmp.csv -f -o$remote_job_folder/indices.txt -F/dev/null > $remote_job_folder/cmd_line_output.txt\n");
        }
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("chmod +x $job_folder/indices");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to bioenv function.
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
    protected function bioenv($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        if(empty($form['upto'])){
            Session::flash('toastr',array('error','You forgot to set the upto parameter!'));
            return false;
        } else {
            $upto = $form['upto'];
        }

        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the method!'));
            return false;
        } else {
            $method_select = $form['method_select'];
        }

        if(empty($form['index'])){
            Session::flash('toastr',array('error','You forgot to set the index parameter!'));
            return false;
        } else {
            $index = $form['index'];
        }

        if(empty($form['trace'])){
            Session::flash('toastr',array('error','You forgot to set the trace parameter!'));
            return false;
        } else {
            $trace = $form['trace'];
        }

        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transformation method!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
        }

        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }

        $params .= ";transpose:".$transpose;
        $params .= ";transformation method:".$transformation_method;
        $params .= ";method:".$method_select;
        $params .= ";Index:".$index;
        $params .= ";upto:".$upto;
        $params .= ";trace:".$trace;

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

        // Build the R script
        if (!($fh = fopen("$job_folder/$job_id.R", "w")))
                exit("Unable to open file: $job_folder/$job_id.R");

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box2\",header = TRUE, sep=\",\",row.names=1);\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\" ,row.names=1);\n");

        if($transpose == "transpose"){
            fwrite($fh, "mat <- t(mat);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }

        fwrite($fh, "otu.ENVFACT.bioenv <- bioenv(mat,ENV,method= \"$method_select\",index = \"$index\",upto=$upto,trace=$trace);\n");
        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "otu.ENVFACT.bioenv\n");
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
     * Handles the part of job submission functionlity that relates to convert2r function.
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
    protected function convert2r($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        $box= $form['box'];

        if(empty($form['header1_id'])){
            Session::flash('toastr',array('error','You forgot to set the header1 parameter!'));
            return false;
        } else {
            $header1 = $form['header1_id'];
        }

        if(empty($form['header2_id'])){
            Session::flash('toastr',array('error','You forgot to set the header2 parameter!'));
            return false;
        } else {
            $header2 = $form['header2_id'];
        }

        if(empty($form['header3_id'])){
            Session::flash('toastr',array('error','You forgot to set the header3 parameter!'));
            return false;
        } else {
            $header3 = $form['header3_id'];
        }

        if(empty($form['header1_fact'])){
            Session::flash('toastr',array('error','You forgot to set the factor header1 parameter!'));
            return false;
        } else {
            $header1_fact = $form['header1_fact'];
        }

        if(empty($form['header2_fact'])){
            Session::flash('toastr',array('error','You forgot to set the factor header2 parameter!'));
            return false;
        } else {
            $header2_fact = $form['header2_fact'];
        }

        if(empty($form['header3_fact'])){
            Session::flash('toastr',array('error','You forgot to set the factor header3 parameter!'));
            return false;
        } else {
            $header3_fact = $form['header3_fact'];
        }

        if(empty($form['function_to_run'])){
            Session::flash('toastr',array('error','You forgot to set the function you want to run!'));
            return false;
        } else {
            $function_to_run = $form['function_to_run'];
        }

        $params .= ";Header 1:".$header1;
        $params .= ";Header 2:".$header2;
        $params .= ";Header 3:".$header3;
        $params .= ";function_to_run:".$function_to_run;
        $params .= ";Factor Header 1:".$header1_fact;
        $params .= ";Factor Header 2:".$header2_fact;
        $params .= ";Factor Header 3:".$header3_fact;

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

        fwrite($fh, "library(reshape);\n");
        fwrite($fh, "geo <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\");\n");
        fwrite($fh, "write.table(geo, file = \"$remote_job_folder/transformed_dataAbu.csv\",sep=\",\",quote = FALSE,row.names = FALSE);\n");
        fwrite($fh, "geoabu<-cast(geo, $header1~$header2, $function_to_run, value=\"$header3\");\n");
        fwrite($fh, "write.table(geoabu, file = \"$remote_job_folder/transformed_dataAbu.csv\",sep=\",\",quote = FALSE,row.names = FALSE);\n");
        fwrite($fh, "geofact = data.frame(geo$$header1_fact,geo$$header2_fact,geo$$header3_fact);\n");
        fwrite($fh, "names(geofact) <- c(\"$header1_fact\",\"$header2_fact\",\"$header3_fact\");\n");
        fwrite($fh, "geofact <- subset(geofact, !duplicated(geofact$$header1_fact));\n");
        fwrite($fh, "rownames(geofact) <- NULL;\n");
        fwrite($fh, "write.table(geofact, file = \"$remote_job_folder/transformed_dataFact.csv\",sep=\",\",quote = FALSE,row.names = FALSE);\n");
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
     * Handles the part of job submission functionlity that relates to mantel function.
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
    protected function mantel($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        $box= $form['box'];
        $box2 = $form['box2'];
        $inputs .= ";".$box2;

        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        if(empty($form['permutations'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        $method_select = $form['method_select'];
        $permutations = $form['permutations'];

        $params .= ";method:".$method_select;
        $params .= ";permutations:".$permutations;

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
        fwrite($fh, "dist1 <- get(load(\"$remote_job_folder/$box\"));\n");
        fwrite($fh, "dist2 <- get(load(\"$remote_job_folder/$box2\"));\n");
        fwrite($fh, "print(\"summary\")\n");


        fwrite($fh, "mantel.out <- mantel(dist1,dist2, method = \"$method_select\",permutations = $permutations)\n");
        fwrite($fh, "mantel.out\n");
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
     * Handles the part of job submission functionlity that relates to permanova function.
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
    protected function permanova($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        $box = $form['box'];

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select a factor file!'));
            return false;
        } else {
            $box2=$form['box2'];
            $inputs .= ";".$box2;
        }

        if(empty($form['transpose'])){
            $transpose = "";
        } else {
            $transpose = $form['transpose'];
        }

        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to select the transformation method!'));
            return false;
        } else {
            $transformation_method=$form['transf_method_select'];
        }

        if(empty($form['column_select'])){
            Session::flash('toastr',array('error','You forgot to select the Factor1 column!'));
            return false;
        } else {
            $column_select=$form['column_select'];
        }

        if(empty($form['column_select2'])){
            Session::flash('toastr',array('error','You forgot to select the Factor2 column!'));
            return false;
        } else {
            $column_select2=$form['column_select2'];
        }

        if(empty($form['permutations'])){
            Session::flash('toastr',array('error','You forgot to set the permutations!'));
            return false;
        } else {
            $permutations=$form['permutations'];
        }

        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to select method!'));
            return false;
        } else {
            $method_select=$form['method_select'];
        }

        if(empty($form['single_or_multi'])){
            Session::flash('toastr',array('error','You forgot to select between single or multiple parameter!'));
            return false;
        } else {
            $single_or_multi=$form['single_or_multi'];
        }

        $params .= ";transpose:".$transpose;
        $params .= ";transformation method:".$transformation_method;
        $params .= ";method:".$method_select;
        $params .= ";permutations:".$permutations;
        $params .= ";formula:".$single_or_multi;
        $params .= ";factor column 1:".$column_select;
        $params .= ";factor column 2:".$column_select2;

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
        fwrite($fh, "ENV <- read.table(\"$remote_job_folder/$box2\",header = TRUE, sep=\",\",row.names=1);\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\" ,row.names=1);\n");
        if($transpose == "transpose"){
            fwrite($fh, "mat <- t(mat);\n");
        }
        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }
        if($single_or_multi =="single"){
            fwrite($fh, "otu.ENVFACT.adonis <- adonis(mat ~ ENV\$$column_select,data=ENV,permutations = $permutations,distance = \"$method_select\");\n");
        }else{
            fwrite($fh, "otu.ENVFACT.adonis <- adonis(mat ~ ENV\$$column_select+ENV\$$column_select2,data=ENV,permutations = $permutations,distance = \"$method_select\");\n");
        }

        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "otu.ENVFACT.adonis\n");
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
     * Handles the part of job submission functionlity that relates to radfit function.
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
    protected function radfit($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

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

        if(!isset($form['column_radfit'])){
            Session::flash('toastr',array('error','You forgot to select a column!'));
            return false;
        } else {
            $column_radfit = $form['column_radfit'];
        }

        $params .= ";transpose:".$transpose;
        $params .= ";transformation method:".$transformation_method;
        $params .= ";community data column:".$column_radfit;

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

        fwrite($fh, "x <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\",row.names=1);\n");

        if($transpose == "transpose"){
            fwrite($fh, "x <- t(x);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "x <- decostand(x, method = \"$transformation_method\");\n");
        }

        fwrite($fh, "library(vegan);\n");
        if($column_radfit == 0){
            fwrite($fh, "mod <- radfit(x)\n");
            fwrite($fh, "png('rplot.png')\n");
            fwrite($fh, "plot(mod)\n");
            fwrite($fh, "dev.off()\n");
            fwrite($fh, "summary(mod);\n");
        } else {
            fwrite($fh, "mod <- radfit(x[$column_radfit,])\n");
            fwrite($fh, "png('rplot.png')\n");
            fwrite($fh, "plot(mod)\n");
            fwrite($fh, "dev.off()\n");
            fwrite($fh, "summary(mod);\n");
        }

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
     * Handles the part of job submission functionlity that relates to simper function.
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
    protected function simper($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        $box= $form['box'];
        $box2 = $form['box2'];
        $inputs .= ";".$box2;

        if(!empty($form['transpose']))
            $transpose = $form['transpose'];
        else
            $transpose = "";

        if(empty($form['column_select'])){
            Session::flash('toastr',array('error','You forgot to select a factor column!'));
            return false;
        } else {
            $column_select = $form['column_select'];
        }

        if(empty($trace = $form['trace'])){
            Session::flash('toastr',array('error','You forgot to set the trace!'));
            return false;
        } else {
            $trace = $form['trace'];
        }

        $permutations = $form['permutations'];

        $params .= ";transpose:".$transpose;
        $params .= ";factor column:".$column_select;
        $params .= ";permutations:".$permutations;
        $params .= ";trace:".$trace;

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

        fwrite($fh, "otu.ENVFACT.simper <- simper(mat,ENV\$$column_select,permutations = $permutations,trace = $trace);\n");
        fwrite($fh, "print(\"summary\")\n");
        fwrite($fh, "otu.ENVFACT.simper\n");
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
     * Handles the part of job submission functionlity that relates to taxa2dist function.
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
    protected function taxa2dist($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        $box= $form['box'];

        if(empty($form['varstep'])){
            Session::flash('toastr',array('error','You forgot to set the varstep parameter!'));
            return false;
        } else {
            $varstep = $form['varstep'];
            $params .= ";varstep:".$varstep;
        }

        if(empty($form['check_taxa2dist'])){
            Session::flash('toastr',array('error','You forgot to set the check parameter!'));
            return false;
        } else {
            $check = $form['check_taxa2dist'];
            $params .= ";check_taxa2dist:".$check;
        }

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

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "agg <- read.table(\"".$remote_job_folder."/".$box."\", header = TRUE, sep=\",\");\n");
        fwrite($fh, "taxdis <- taxa2dist(agg, varstep=$varstep, check=$check);\n");
        fwrite($fh, "save(taxdis, ascii=TRUE, file = \"$remote_job_folder/taxadis.csv\");\n");
        fwrite($fh, "summary(taxdis);\n");
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
     * Handles the part of job submission functionlity that relates to vegdist function.
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
    protected function vegdist($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        $box = $form['box'];

        if(empty($form['transpose'])){
            $transpose = "";
            $params .= ";transpose: ";
        } else {
            $transpose = $form['transpose'];
            $params .= ";transpose:".$transpose;
        }

        if(empty($form['transf_method_select'])){
            Session::flash('toastr',array('error','You forgot to set the transf_method_select parameter!'));
            return false;
        } else {
            $transformation_method = $form['transf_method_select'];
            $params .= ";transofrmation method:".$transformation_method;
        }

        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the method_select parameter!'));
            return false;
        } else {
            $method_select = $form['method_select'];
            $params .= ";method:".$method_select;
        }

        if(empty($form['binary_select'])){
            Session::flash('toastr',array('error','You forgot to set the binary_select parameter!'));
            return false;
        } else {
            $bin = $form['binary_select'];
            $params .= ";binary:".$bin;
        }

        if(empty($form['diag_select'])){
            Session::flash('toastr',array('error','You forgot to set the diag_select parameter!'));
            return false;
        } else {
            $diag = $form['diag_select'];
            $params .= ";diag:".$diag;
        }

        if(empty($form['upper_select'])){
            Session::flash('toastr',array('error','You forgot to set the upper_select parameter!'));
            return false;
        } else {
            $upper = $form['upper_select'];
            $params .= ";upper:".$upper;
        }

        if(empty($form['na_select'])){
            Session::flash('toastr',array('error','You forgot to set the na_select parameter!'));
            return false;
        } else {
            $na = $form['na_select'];
            $params .= ";na.rm:".$na;
        }

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

        fwrite($fh, "library(vegan);\n");
        fwrite($fh, "mat <- read.table(\"$remote_job_folder/$box\", header = TRUE, sep=\",\",row.names=1);\n");

        if($transpose == "transpose"){
            fwrite($fh, "mat <- t(mat);\n");
        }

        if($transformation_method != "none"){
            fwrite($fh, "mat <- decostand(mat, method = \"$transformation_method\");\n");
        }

        fwrite($fh, "vegdist <- vegdist(mat, method = \"$method_select\",binary=$bin, diag=$diag, upper=$upper,na.rm = $na)\n");
        fwrite($fh, "save(vegdist, ascii=TRUE, file = \"$remote_job_folder/vegdist.csv\");\n");
        fwrite($fh, "summary(vegdist);\n");
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