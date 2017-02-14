<?php

namespace App\Http\Controllers;

use Session;
use App\Http\Controllers\JobController;

/**
 * ....
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class ParallelController extends JobController {

    public function __construct() {
        parent::__construct();
    }

    /**
     * Handles the part of job submission functionlity that relates to parallel_anosim function.
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
    protected function parallel_anosim($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        $box= $form['box'];
        $box2 = $form['box2'];
        $inputs .= ";".$box2;

        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the method_select parameter!'));
            return false;
        } else {
            $method_select = $form['method_select'];
        }

        if(empty($form['permutations'])){
            Session::flash('toastr',array('error','You forgot to set the permutations parameter!'));
            return false;
        } else {
            $permutations = $form['permutations'];
        }

        if(empty($form['column_select'])){
            Session::flash('toastr',array('error','You forgot to select the column of factor file!'));
            return false;
        } else {
            $column_select = $form['column_select'];
        }

        if(empty($form['No_of_processors'])){
            Session::flash('toastr',array('error','You forgot to set the number of processors!'));
            return false;
        } else {
            $no_of_processors = $form['No_of_processors'];
        }

        if(empty($form['transpose'])){
            $transpose = "FALSE";
        } else {
            $transpose = 'TRUE';
        }

        $params .= ";method_select:".$method_select;
        $params .= ";permutations:".$permutations;
        $params .= ";No_of_processors:".$no_of_processors;
        $params .= ";transpose:".$transpose;
        $params .= ";column_select:".$column_select;

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
        $script_source = app_path().'/rvlab/files/anosimMPI_24_09_2015.r';
        copy($script_source,"$job_folder/".$job_id.".R");

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
        fwrite($fh2, "#PBS -l nodes=1:ppn=".$no_of_processors."\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $remote_job_folder/$job_id.R $remote_job_folder/$box $transpose $remote_job_folder/$box2 $column_select $remote_job_folder/ $permutations $method_select > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to parallel_bioenv function.
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
    protected function parallel_bioenv($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        $box= $form['box'];
        $box2 = $form['box2'];
        $inputs .= ";".$box2;

        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the method_select parameter!'));
            return false;
        } else {
            $method_select = $form['method_select'];
        }

        if(empty($form['index_select'])){
            Session::flash('toastr',array('error','You forgot to set the method_select parameter!'));
            return false;
        } else {
            $index_select = $form['index_select'];
        }

        if(empty($form['trace'])){
            Session::flash('toastr',array('error','You forgot to set the trace parameter!'));
            return false;
        } else {
            $trace = $form['trace'];
        }


        if(empty($form['upto'])){
            Session::flash('toastr',array('error','You forgot to set the upto parameter!'));
            return false;
        } else {
            $upto = $form['upto'];
        }

        if(empty($form['No_of_processors'])){
            Session::flash('toastr',array('error','You forgot to set the number of processors!'));
            return false;
        } else {
            $no_of_processors = $form['No_of_processors'];
        }

        if(empty($form['transpose'])){
            $transpose = "FALSE";
        } else {
            $transpose = 'TRUE';
        }

        $params .= ";transpose:".$form['transpose'];
        $params .= ";Number of processors:".$no_of_processors;
        $params .= ";method:".$method_select;
        $params .= ";Index:".$index_select;
        $params .= ";trace:".$trace;
        $params .= ";upto:".$upto;

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
        $script_source = app_path().'/rvlab/files/parallel_bioenv_MPI.r';
        copy($script_source,"$job_folder/".$job_id.".R");

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
        fwrite($fh2, "#PBS -l nodes=1:ppn=$no_of_processors\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $remote_job_folder/$job_id.R $remote_job_folder/$box $transpose $remote_job_folder/$box2 $remote_job_folder/ $method_select $index_select $upto $trace $index_select > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to parallel_mantel function.
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
    protected function parallel_mantel($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        $box= $form['box'];
        $box2 = $form['box2'];
        $inputs .= ";".$box2;

        if(empty($form['method_select'])){
            Session::flash('toastr',array('error','You forgot to set the method_select parameter!'));
            return false;
        } else {
            $method_select = $form['method_select'];
        }

        if(empty($form['permutations'])){
            Session::flash('toastr',array('error','You forgot to set the permutations parameter!'));
            return false;
        } else {
            $permutations = $form['permutations'];
        }

        if(empty($form['No_of_processors'])){
            Session::flash('toastr',array('error','You forgot to set the number of processors!'));
            return false;
        } else {
            $no_of_processors = $form['No_of_processors'];
        }

        $params .= ";Number of processors:".$no_of_processors;
        $params .= ";permutations:".$permutations;
        $params .= ";method:".$method_select;

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
        $script_source = app_path().'/rvlab/files/mantelMPI_24_09_2015.r';
        copy($script_source,"$job_folder/".$job_id.".R");

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
        fwrite($fh2, "#PBS -l nodes=1:ppn=".$no_of_processors."\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $remote_job_folder/$job_id.R $remote_job_folder/$box FALSE $remote_job_folder/$box2 FALSE $remote_job_folder/ $method_select $permutations > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;

    }

    /**
     * Handles the part of job submission functionlity that relates to parallel_permanova function.
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
    protected function parallel_permanova($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        $box = $form['box'];

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select a factor file!'));
            return false;
        } else {
            $box2=$form['box2'];
            $inputs .= ";".$box2;
        }

        if(empty($form['transpose'])){
            $transpose = "FALSE";
        } else {
            $transpose = "TRUE";
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
            if($form['single_or_multi'] == 'single')
                $single_or_multi = 1;
            else
                $single_or_multi = 2;
        }

        if(empty($form['No_of_processors'])){
            Session::flash('toastr',array('error','You forgot to set the number of processors!'));
            return false;
        } else {
            $no_of_processors = $form['No_of_processors'];
        }

        $params .= ";transpose:".$form['transpose'];
        $params .= ";single_or_multi:".$form['single_or_multi'];
        $params .= ";factor column 1:".$column_select;
        $params .= ";factor column 2:".$column_select2;
        $params .= ";permutations:".$permutations;
        $params .= ";method:".$method_select;
        $params .= ";Number of processors:".$no_of_processors;

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
        $script_source = app_path().'/rvlab/files/permanovaMPI_24_09_2015.r';
        copy($script_source,"$job_folder/".$job_id.".R");

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
        fwrite($fh2, "#PBS -l nodes=1:ppn=".$no_of_processors."\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $remote_job_folder/$job_id.R $remote_job_folder/$box $transpose $remote_job_folder/$box2 $single_or_multi $column_select $column_select2 $remote_job_folder/ $permutations $method_select > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to parallel_postgres_taxa2dist function.
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
    protected function parallel_postgres_taxa2dist($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        // Retrieve function configuration
        if(empty($form['No_of_processors'])){
            Session::flash('toastr',array('error','You forgot to set the check parameter!'));
            return false;
        } else {
            $no_of_processors = $form['No_of_processors'];
        }

        if(empty($form['varstep'])){
            Session::flash('toastr',array('error','You forgot to set the varstep parameter!'));
            return false;
        } else {
            $varstep = $form['varstep'];
        }

        if(empty($form['check_parallel_taxa2dist'])){
            Session::flash('toastr',array('error','You forgot to set the check parameter!'));
            return false;
        } else {
            $check = $form['check_parallel_taxa2dist'];
        }

        $params .= ";Number of processors:".$no_of_processors;
        $params .= ";varstep:".$varstep;
        $params .= ";check:".$check;

        $box = $form['box'];
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        $this->log_event($workspace_filepath. ' - '.$job_filepath,'info');
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        // Build the R script
        $script_source = app_path().'/rvlab/files/taxa2distPostgresMPI.r';
        copy($script_source,"$job_folder/".$job_id.".R");

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
        fwrite($fh2, "#PBS -l nodes=1:ppn=$no_of_processors\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $job_id.R $remote_job_folder/$box 1000000 $remote_job_folder/ $remote_job_folder/ $job_id $varstep $check  > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to parallel_simper function.
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
    protected function parallel_simper($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        $box= $form['box'];
        $box2 = $form['box2'];
        $inputs .= ";".$box2;

        if(empty($form['trace'])){
            Session::flash('toastr',array('error','You forgot to set the method_select parameter!'));
            return false;
        } else {
            $trace = $form['trace'];
        }

        if(empty($form['permutations'])){
            Session::flash('toastr',array('error','You forgot to set the permutations parameter!'));
            return false;
        } else {
            $permutations = $form['permutations'];
        }

        if(empty($form['column_select'])){
            Session::flash('toastr',array('error','You forgot to select the column of factor file!'));
            return false;
        } else {
            $column_select = $form['column_select'];
        }

        if(empty($form['No_of_processors'])){
            Session::flash('toastr',array('error','You forgot to set the number of processors!'));
            return false;
        } else {
            $no_of_processors = $form['No_of_processors'];
        }

        if(empty($form['transpose'])){
            $transpose = "FALSE";
        } else {
            $transpose = 'TRUE';
        }

        $params .= ";transpose:".$form['transpose'];
        $params .= ";Number of processors:".$no_of_processors;
        $params .= ";column_select:".$column_select;
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
        $script_source = app_path().'/rvlab/files/parallel_simper.r';
        copy($script_source,"$job_folder/".$job_id.".R");

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
        fwrite($fh2, "#PBS -l nodes=1:ppn=$no_of_processors\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $remote_job_folder/$job_id.R $remote_job_folder/$box $transpose $remote_job_folder/$box2 $column_select $remote_job_folder/ $permutations $trace > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to parallel_taxa2dist function.
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
    protected function parallel_taxa2dist($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){
        // Retrieve function configuration
        if(empty($form['No_of_processors'])){
            Session::flash('toastr',array('error','You forgot to set the check parameter!'));
            return false;
        } else {
            $no_of_processors = $form['No_of_processors'];
        }

        if(empty($form['varstep'])){
            Session::flash('toastr',array('error','You forgot to set the varstep parameter!'));
            return false;
        } else {
            $varstep = $form['varstep'];
        }

        if(empty($form['check_parallel_taxa2dist'])){
            Session::flash('toastr',array('error','You forgot to set the check parameter!'));
            return false;
        } else {
            $check = $form['check_parallel_taxa2dist'];
        }

        $params .= ";Number of processors:".$no_of_processors;
        $params .= ";varstep:".$varstep;
        $params .= ";check:".$check;

        $box = $form['box'];
        // Move input file from workspace to job's folder
        $workspace_filepath = $user_workspace.'/'.$box;
        $job_filepath = $job_folder.'/'.$box;
        $this->log_event($workspace_filepath. ' - '.$job_filepath,'info');
        if(!copy($workspace_filepath,$job_filepath)){
            $this->log_event('Moving file from workspace to job folder, failed.',"error");
            throw new Exception();
        }

        // Build the R script
        $script_source = app_path().'/rvlab/files/Taxa2DistMPI.r';
        copy($script_source,"$job_folder/".$job_id.".R");

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
        fwrite($fh2, "#PBS -l nodes=1:ppn=$no_of_processors\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $job_id.R $remote_job_folder/$box $remote_job_folder/ $remote_job_folder/ TRUE $varstep $check  output  > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

    /**
     * Handles the part of job submission functionlity that relates to parallel_taxa2taxon function.
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
    protected function parallel_taxa2taxon($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,&$inputs,&$params){

        if(empty($form['box2'])){
            Session::flash('toastr',array('error','You forgot to select an input file!'));
            return false;
        }

        $box= $form['box'];
        $box2 = $form['box2'];
        $inputs .= ";".$box2;

        if(empty($form['No_of_processors'])){
            Session::flash('toastr',array('error','You forgot to set the number of processors!'));
            return false;
        } else {
            $no_of_processors = $form['No_of_processors'];
        }

        if(empty($form['varstep'])){
            Session::flash('toastr',array('error','You forgot to set the varstep parameter!'));
            return false;
        } else {
            $varstep = $form['varstep'];
        }

        $params .= ";Number of processors:".$no_of_processors;
        $params .= ";varstep:".$varstep;

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
        $script_source = app_path().'/rvlab/files/taxa2dist_taxondive.r';
        copy($script_source,"$job_folder/".$job_id.".R");

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
        fwrite($fh2, "#PBS -l nodes=1:ppn=".$no_of_processors."\n");    // Use 1 node and 1 CPU from this node
        fwrite($fh2, "date\n");
        fwrite($fh2, "mpiexec /usr/bin/Rscript $remote_job_folder/$job_id.R $remote_job_folder/$box TRUE $remote_job_folder/$box2 TRUE 39728447488 $remote_job_folder/ $varstep > $remote_job_folder/cmd_line_output.txt\n");
        fwrite($fh2, "date\n");
        fwrite($fh2, "exit 0");
        fclose($fh2);

        // Execute the bash script
        system("chmod +x $job_folder/$job_id.pbs");
        system("$job_folder/$job_id.pbs > /dev/null 2>&1 &");
        return true;
    }

}