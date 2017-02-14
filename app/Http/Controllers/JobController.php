<?php

namespace App\Http\Controllers;

use DB;
use View;
use Session;
use Redirect;
use Response;
use App\Models\Job;
use App\Models\JobsLog;
use App\Models\WorkspaceFile;
use App\Extras\RvlabParser;
use App\Http\Controllers\CommonController;
use Illuminate\Http\Request;

/**
 * Implements functionality related to job submission, job status refreshing and building the results page.
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class JobController extends CommonController {

    protected $workspace_path;
    protected $jobs_path;
    protected $remote_jobs_path;
    protected $remote_workspace_path;

    public function __construct() {
        parent::__construct();

        $this->workspace_path = config('rvlab.workspace_path');
        $this->jobs_path = config('rvlab.jobs_path');
        $this->remote_jobs_path = config('rvlab.remote_jobs_path');
        $this->remote_workspace_path = config('rvlab.remote_workspace_path');

        // Check if cluster storage has been mounted to web server
        if(!$this->check_storage()){
            if($this->is_mobile){
                $response = array('message','Storage not found');
                return Response::json($response,500);
                die();
            } else {
                echo $this->load_view('errors/unmounted','Storage not found');
                die();
            }
        }
    }

    /**
     * Displays the Home Page
     *
     * @return View
     */
    public function index() {
        $userInfo = session('user_info');
        $user_email = $userInfo['email'];

        $job_list = DB::table('jobs')->where('user_email',$user_email)->orderBy('id','desc')->get();
        $r_functions = config('r_functions.list');
        $form_data['workspace_files'] = WorkspaceFile::getUserFiles($user_email);
        $form_data['user_email'] = $user_email;
        $form_data['tooltips'] = config('tooltips');

        if($this->is_mobile){
            $mobile_functions = config('mobile_functions.list');
            $response = array(
                'r_functions'   =>  $mobile_functions,
                'job_list'      =>  $job_list,
                'workspace_files'   =>  $form_data['workspace_files'],
            );
            return Response::json($response,200);
        } else {

            $forms = array();
            foreach($r_functions as $codename => $title){
                $forms[$codename] = View::make('forms.'.$codename,$form_data);
            }

            $data['forms'] = $forms;
            $data['job_list'] = $job_list;
            $data['r_functions'] = $r_functions;
            $data2['workspace_files'] = $form_data['workspace_files'];
            $data['count_workspace_files'] = $form_data['workspace_files']->count();
            $data['workspace'] = View::make('workspace.manage',$data2);
            $data['is_admin'] = session('is_admin');
            $data['refresh_rate'] = $this->system_settings['status_refresh_rate_page'];
            $data['timezone'] = $userInfo['timezone'];
            //$data['refresh_rate'] = $this->system_settings['status_refresh_rate_page'];

            // Check if this we load the page after delete_many_jobs() has been called
            if(Session::has('deletion_info')){
                $data['deletion_info'] = Session::get('deletion_info');
            }

            //
            if(Session::has('workspace_tab_status')){
                $data['workspace_tab_status'] = Session::get('workspace_tab_status');
            } else {
                $data['workspace_tab_status'] = 'closed';
            }

            //
            if(Session::has('last_function_used')){
                $data['last_function_used'] = Session::get('last_function_used');
            } else {
                $data['last_function_used'] = "taxa2dist";
            }

            return $this->load_view('index','R vLab Home Page',$data);
        }

    }

    /**
     *
     * @return \RedirectResponseDeletes selected jobs
     *
     * @return RedirectResponse
     */
    public function delete_many_jobs(Request $request){

        $form = $request->all();

        if(!empty($form['jobs_for_deletion'])){
            $job_list_string = $form['jobs_for_deletion'];
            $job_list = explode(';',$job_list_string);

            $total_success = true;
            $error_messages = array();
            $count_deleted = 0;

            foreach($job_list  as $job_id){
                $result = $this->delete_one_job($job_id);
                if($result['deleted']){
                    $count_deleted++;
                } else {
                    $total_success = false;
                    $error_messages[] = $result['message'];
                }
            }

            $deletion_info = array(
                'total'     =>  count($job_list),
                'deleted'   =>  $count_deleted,
                'messages'  =>  $error_messages
            );

            if($this->is_mobile){
                return Response::json($deletion_info,200);
            } else {
                return Redirect::to('/')->with('deletion_info',$deletion_info);
            }
        } else {
            return Redirect::to('/');
        }

    }

    /**
     * Deletes a specific job
     *
     * @param int $job_id
     * @return array
     */
    protected function delete_one_job($job_id){
        $userInfo = session('user_info');
        $job = Job::find($job_id);
        $user_email = $userInfo['email'];

        // Check if this job exists
        if(empty($job)){
            $this->log_event("User tried to delete a job that does not exist.","illegal");
            return array(
                'deleted'   =>  false,
                'message'   =>  'You have tried to delete a job ('.$job_id.') that does not exist'
            );
        }

        // Check if this job belongs to this user
        if($job->user_email != $user_email){
            $this->log_event("User tried to delete a job that does not belong to him.","unauthorized");
            return array(
                'deleted'   =>  false,
                'message'   =>  'You have tried to delete a job that does not belong to you.'
            );
        }

        // Check if the job has finished running
        if(in_array($job->status,array('running','queued','submitted'))){
            $this->log_event("User tried to delete a job that is not finished.","illegal");
            return array(
                'deleted'   =>  false,
                'message'   =>  'You have tried to delete a job ('.$job_id.') that is not finished.'
            );
        }

        try {
            // Delete job record
            Job::where('id',$job_id)->delete();

            // Delete job files
            $job_folder = $this->jobs_path.'/'.$user_email.'/job'.$job_id;
            if(!delTree($job_folder)){
                $this->log_event('Folder '.$job_folder.' could not be deleted!',"error");
                return array(
                    'deleted'   =>  false,
                    'message'   =>  'Unexpected error occured during job folder deletion ('.$job_id.').'
                );
            }

            return array(
                'deleted'   =>  true,
                'message'   =>  ''
            );
        } catch (Exception $ex) {
            $this->log_event("Error occured during deletion of job".$job_id.". Message: ".$ex->getMessage(),"error");
            return array(
                'deleted'   =>  false,
                'message'   =>  'Unexpected error occured during deletion of a job ('.$job_id.').'
            );
        }

    }

    /**
     * Retrieves the list of jobs in user's workspace
     *
     * @return JSON
     */
    public function get_user_jobs(){
        $userInfo = session('user_info');

        if (!empty($userInfo)) {
            $user_email = $userInfo['email'];
            $timezone = $userInfo['timezone'];
            $job_list = Job::where('user_email', $user_email)->orderBy('id', 'desc')->get();
            foreach ($job_list as $job) {
                $job->submitted_at = dateToTimezone($job->submitted_at, $timezone);
                $job->started_at = dateToTimezone($job->started_at, $timezone);
                $job->completed_at = dateToTimezone($job->completed_at, $timezone);
            }
            $json_list = $job_list->toArray();
            return Response::json($json_list, 200);
        } else {
            return Response::json(array('message' => 'You are not logged in or your session has expired!'), 401);
        }
    }

    /**
     * Retrieves the status of a submitted job
     *
     * @param int $job_id
     * @return JSON
     */
    public function get_job_status($job_id){
        $userInfo = session('user_info');
        $user_email = $userInfo['email'];

        // Check if this job belongs to this user
        $result = DB::table('jobs')
                    ->where('id',$job_id)
                    ->where('user_email',$user_email)
                    ->first();

        if(empty($result)){
            $this->log_event("Trying to retrieve status for a job that does not belong to this user.","unauthorized");
            $response = array('message','Trying to retrieve status for a job that does not belong to this user!');
            return Response::json($response,401);
        }

        return Response::json(array('status' => $result->status),200);

    }

    /**
     * Retrieves the R script used in the execution of a submitted job.
     *
     * @param int $job_id
     * @return JSON
     */
    public function get_r_script($job_id){
        $userInfo = session('user_info');
        $user_email = $userInfo['email'];
        $job_folder = $this->jobs_path.'/'.$user_email.'/job'.$job_id;
        $fullpath = $job_folder.'/job'.$job_id.'.R';

        // Check if the R script exists
        if(!file_exists($fullpath)){
            $this->log_event("Trying to retrieve non existent R script.","illegal");
            $response = array('message','Trying to retrieve non existent R script!');
            return Response::json($response,400);
        }

        // Check if this job belongs to this user
        $result = DB::table('jobs')
                    ->where('id',$job_id)
                    ->where('user_email',$user_email)
                    ->first();

        if(empty($result)){
            $this->log_event("Trying to retrieve an R script from a job that does not belong to this user.","unauthorized");
            $response = array('message','Trying to retrieve an R script from a job that does not belong to this user!');
            return Response::json($response,401);
        }

        $r = file($fullpath);
        return Response::json($r,200);


    }

    /**
     * Retrieves a file from a job's folder.
     *
     * @param int $job_id
     * @param string $filename
     * @return View|file|JSON
     */
    public function get_job_file($job_id,$filename){

        $userInfo = session('user_info');
        $user_email = $userInfo['email'];
        $job_folder = $this->jobs_path.'/'.$user_email.'/job'.$job_id;
        $fullpath = $job_folder.'/'.$filename;

        if(!file_exists($fullpath)){
            $this->log_event("Trying to retrieve non existent file.","illegal");
            if($this->is_mobile){
                $response = array('message','Trying to retrieve non existent file!');
                return Response::json($response,400);
            } else {
                return $this->illegalAction();
            }
        }

        // Check if this job belongs to this user
        $result = DB::table('jobs')
                    ->where('id',$job_id)
                    ->where('user_email',$user_email)
                    ->first();

        if(!empty($result)){
            $parts = pathinfo($filename);
            $new_filename = $parts['filename'].'_job'.$job_id.'.'.$parts['extension'];

            switch($parts['extension']){
                case 'png':
                    return response()->download($fullpath, $new_filename, ['Content-Type'=>'image/png']);
                    break;
                case 'csv':
                    return response()->download($fullpath, $new_filename, ['Content-Type'=>'text/plain','Content-Disposition'=>'attachment; filename='.$new_filename]);
                    break;
                case 'nwk':
                case 'pdf':
                    return response()->download($fullpath, $new_filename, ['Content-Type'=>'application/octet-stream']);
                    break;
            }

        } else {
            $this->log_event("Trying to retrieve a file that does not belong to a user's job.","unauthorized");
            if($this->is_mobile){
                $response = array('message',"Trying to retrieve a file that does not belong to a user's job");
                return Response::json($response,401);
            } else {
                abort(403, 'Unauthorized action.');
            }
        }

    }

    /**
     *
     * @param int $job_id Refreshes the status of a specific job
     *
     * @param int $job_id
     * @return void
     */
    protected function refresh_single_status($job_id){
        $job = Job::find($job_id);

        $job_folder = $this->jobs_path.'/'.$job->user_email.'/job'.$job_id;
        $pbs_filepath = $job_folder.'/job'.$job->id.'.pbs';
        $submitted_filepath = $job_folder.'/job'.$job->id.'.submitted';

        if(file_exists($pbs_filepath)){
            $status = 'submitted';
        } else if(!file_exists($submitted_filepath)){
            $status = 'creating';
        } else {
            $status_file = $job_folder.'/job'.$job_id.'.jobstatus';
            $status_info = file($status_file);
            $status_parts = preg_split('/\s+/', $status_info[0]);
            $status_message = $status_parts[8];
            switch($status_message){
                case 'Q':
                    $status = 'queued';
                    break;
                case 'R':
                    $status = 'running';
                    $started_at = $status_parts[3].' '.$status_parts[4];
                    break;
                case 'ended':
                    $status = 'completed';
                    $started_at = $status_parts[3].' '.$status_parts[4];
                    $completed_at = $status_parts[5].' '.$status_parts[6];
                    break;
                case 'ended_PBS_ERROR':
                    $status = 'failed';
                    $started_at = $status_parts[3].' '.$status_parts[4];
                    $completed_at = $status_parts[5].' '.$status_parts[6];
                    break;
            }

            switch($job->function){
                case 'bict':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
                case 'parallel_taxa2dist':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
                case 'parallel_postgres_taxa2dist':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
                case 'parallel_anosim':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
                case 'parallel_mantel':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
                case 'parallel_taxa2taxon':
                        $fileToParse = '/cmd_line_output.txt';
                        break;
                case 'parallel_permanova':
                        $fileToParse = '/cmd_line_output.txt';
                        break;
                case 'parallel_bioenv':
                        $fileToParse = '/cmd_line_output.txt';
                        break;
                case 'parallel_simper':
                        $fileToParse = '/cmd_line_output.txt';
                        break;
                default:
                    $fileToParse = '/job'.$job_id.'.Rout';
            }

            // If job has run, check for R errors
            if($status == 'completed'){
                $parser = new RvlabParser();
                $parser->parse_output($job_folder.$fileToParse);
                if($parser->hasFailed()){
                    $status = 'failed';
                    //$this->log_event(implode(' - ',$parser->getOutput()),"info");
                }
            }
        }

        $job->status = $status;
        $job->save();

        // IF job was completed successfully use it for statistics
        if($status == 'completed'){
            $job_log = new JobsLog();
            $job_log->id = $job->id;
            $job_log->user_email = $job->user_email;
            $job_log->function = $job->function;
            $job_log->status = $job->status;
            $job_log->submitted_at = $job->submitted_at;
            $job_log->started_at = $job->started_at;
            $job_log->completed_at = $job->completed_at;
            $job_log->jobsize = $job->jobsize;
            $job_log->inputs = $job->inputs;
            $job_log->parameters = $job->parameters;
            $job_log->save();
        } else if(($status == 'running')&&(empty($job_log->started_at))){
            $job_log->started_at = $job->started_at;
            $job_log->save();
        }
    }

    /**
     * Displays the results page of a job
     *
     * @param int $job_id
     * @return View|JSON
     */
    public function job_page($job_id){

        $userInfo = session('user_info');
        $user_email = $userInfo['email'];
        $job_record = DB::table('jobs')->where('user_email',$user_email)->where('id',$job_id)->first();
        $data['job'] = $job_record;

        // In case job id wasn't found
        if(empty($job_record)){
            Session::flash('toastr',array('error','You have not submitted recently any job with such ID!'));
            if($this->is_mobile){
                $response = array('message','You have not submitted recently any job with such ID!');
                return Response::json($response,400);
            } else {
                return Redirect::back();
            }
        }

        // Load information about input files
        $inputs = array();
        $input_files = explode(';',$job_record->inputs);
        foreach($input_files as $ifile){
            $info = explode(':',$ifile);
            $id = $info[0];
            $filename = $info[1];
            $record = WorkspaceFile::where('id',$id)->first();
            if(empty($record)){
                $exists = false;
            } else {
                $exists = true;
            }
            $inputs[] = array(
                'id'    =>  $id,
                'filename'  =>  $filename,
                'exists'    =>  $exists
            );
        }

        // If job execution has not finished, try to update its status
        if(in_array($job_record->status,array('submitted','running','queued'))){
            $this->refresh_single_status($job_id);
        }

        $selected_function = $job_record->function;
        $data['function'] = $selected_function;

        // Decide which file should be parsed
        switch($selected_function){
            case 'simper':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
            case 'bict':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_anosim':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_taxa2dist':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_postgres_taxa2dist':
                    $fileToParse = '/cmd_line_output.txt';
                    break;
            case 'parallel_mantel':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_taxa2taxon':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_permanova':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_bioenv':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_simper':
                $fileToParse = '/cmd_line_output.txt';
                break;
            default:
                $fileToParse = '/job'.$job_id.'.Rout';
        }

        // If job has failed
        if($job_record->status == 'failed'){
            $job_folder = $this->jobs_path.'/'.$job_record->user_email.'/job'.$job_id;
            $log_file = $job_folder."/job".$job_id.".log";

            $parser = new RvlabParser();
            $parser->parse_output($job_folder.$fileToParse);
            if($parser->hasFailed()){
                $data['errorString'] = implode("<br>",$parser->getOutput());
                $data['errorString'] .= $parser->parse_log($log_file);
            } else {
                $data['errorString'] = "Error occured during submission.";
                $data['errorString'] .= $parser->parse_log($log_file);
            }
            if($this->is_mobile){
                $response = array('message','Error occured during submission.');
                return Response::json($response,500);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        // If job is pending
        if(in_array($job_record->status,array('submitted','queued','running'))){
            if($this->is_mobile){
                $response = array('data',$data);
                return Response::json($response,500);
            } else {
                $data['refresh_rate'] = $this->system_settings['status_refresh_rate_page'];
                return $this->load_view('results/submitted','Job Results',$data);
            }
        }

        $job_folder = $this->jobs_path.'/'.$user_email.'/job'.$job_id;
        $user_workspace = $this->workspace_path.'/'.$user_email;

        // Build the result page for this job
        $low_function = strtolower($selected_function);
        return $this->{$low_function.'_results'}($job_id,$job_folder,$user_workspace,$inputs);

    }

    /**
     * Submits a new job
     * Handles the basic functionlity of submission that is not related to a specific R vLab function
     *
     * @return RedirectResponse|JSON
     */
    public function submit(Request $request){
        try {
            $form = $request->all();
            $function_select = $form['function'];
            $userInfo = session('user_info');
            $user_email = $userInfo['email'];

            // Validation
            if(empty($form['box'])) {
                if($this->is_mobile){
                    $response = array('message','You forgot to select an input file!');
                    return Response::json($response,400);
                } else {
                    Session::flash('toastr',array('error','You forgot to select an input file!'));
                    return Redirect::back();
                }
            } else {
                $box = $form['box'];
            }

        } catch(Exception $ex){
            $this->log_event($ex->getMessage(),"error");
        }

        try {
            // Create a job record
            $job = new Job();
            $job->user_email = $user_email;
            $job->function = $function_select;
            $job->status = 'creating';
            $job->submitted_at = date("Y-m-d H:i:s");
            $job->save();

            // Get the job id and create the job folder
            $job_id = 'job'.$job->id;
            $user_jobs_path = $this->jobs_path.'/'.$user_email;
            $job_folder = $user_jobs_path.'/'.$job_id;
            $user_workspace = $this->workspace_path.'/'.$user_email;


            // Create the required folders if they are not exist
            if(!file_exists($user_workspace)){
                if(!mkdir($user_workspace)){
                    $this->log_event('User workspace directory could not be created!','error');
                    if($this->is_mobile){
                        $response = array('message','User workspace directory could not be created!');
                        return Response::json($response,500);
                    } else {
                        return $this->unexpected_error();
                    }
                }
            }
            if(!file_exists($user_jobs_path)){
                if(!mkdir($user_jobs_path)){
                    $this->log_event('User jobs directory could not be created!','error');
                    if($this->is_mobile){
                        $response = array('message','User jobs directory could not be created!');
                        return Response::json($response,500);
                    } else {
                        return $this->unexpected_error();
                    }
                }
            }

            if(!file_exists($job_folder)){
                if(!mkdir($job_folder)){
                    $this->log_event('Job directory could not be created!','error');
                    if($this->is_mobile){
                        $response = array('message','Job directory could not be created!');
                        return Response::json($response,500);
                    } else {
                        return $this->unexpected_error();
                    }
                }
            }
            $remote_job_folder = $this->remote_jobs_path.'/'.$user_email.'/'.$job_id;
            $remote_user_workspace = $this->remote_workspace_path.'/'.$user_email;

            // Run the function
            $params = "";
            if(is_array($form['box']))
                $inputs = implode(';',$form['box']);
            else
                $inputs = $form['box'];
            $low_function = strtolower($function_select);
            $submitted = $this->{$low_function}($form,$job_id,$job_folder,$remote_job_folder,$user_workspace,$remote_user_workspace,$inputs,$params);
            if(!$submitted){

                $this->log_event('Function '.$low_function.' failed!',"error");

                // Delete the job record
                Job::where('id',$job->id)->delete();

                 // Delete folder if created
                if(file_exists($job_folder)){
                    if(!delTree($job_folder)){
                        $this->log_event('Folder '.$job_folder.' could not be deleted after failed job submission!',"error");
                    }
                }

                if($this->is_mobile){
                    $message = 'New job submission failed!';
                    // Add as part of the message the specialized error message that has been loaded to session flash
                    if(Session::has('toastr')){
                        $toastr = Session::get('toastr');
                        foreach($toastr as $error){
                            $message .= " - ".$error;
                        }
                    }
                    $response = array('message',$message);
                    return Response::json($response,500);
                } else {
                    return Redirect::back();
                }
            }

            $input_ids = array();
            $inputs_list = explode(';',$inputs);
            foreach($inputs_list as $input){
                $file_record = WorkspaceFile::whereRaw('BINARY filename LIKE ?',array($input))->where('user_email', $user_email)->first();
                $input_ids[] = $file_record->id.":".$input;
            }
            $input_ids_string = implode(';',$input_ids);


            $job->status = 'submitted';
            $job->jobsize = directory_size($job_folder);
            $job->inputs = $input_ids_string;
            $job->parameters = trim($params,";");
            $job->save();

        } catch (Exception $ex) {
            // Delete record if created
            if(!empty($job_id)){
                $job->delete();
            }
            // Delete folder if created
            if(file_exists($job_folder)){
                if(!delTree($job_folder)){
                    $this->log_event('Folder '.$job_folder.' could not be deleted!',"error");
                }
            }

            $this->log_event($ex->getMessage(),"error");
            Session::flash('toastr',array('error','New job submission failed!'));
            if($this->is_mobile){
                $response = array('message','New job submission failed!');
                return Response::json($response,500);
            } else {
                return Redirect::back();
            }
        }

        Session::put('last_function_used',$function_select);
        Session::flash('toastr',array('success','The job submitted successfully!'));
        //$this->log_event("New job submission","info");
        if($this->is_mobile){
            return Response::json(array(),200);
        } else {
             return Redirect::to('/');
        }

    }

    /**
     * Loads job results information related to taxa2dist.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function taxa2dist_results($job_id,$job_folder,$user_workspace,$input_files){

        $data = array();
        $data['function'] = "taxa2dist";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "taxadis";
        $data['blue_disk_extension'] = '.csv';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }

    }

    /**
     * Loads job results information related to taxondive.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function taxondive_results($job_id,$job_folder,$user_workspace,$input_files){

        $data = array();
        $data['function'] = "taxondive";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png','legend.png');
        $data['dir_prefix'] = "taxondive";
        $data['blue_disk_extension'] = '.csv';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }

    }

    /**
     * Loads job results information related to vegdist.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function vegdist_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "vegdist";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "vegdist";
        $data['blue_disk_extension'] = '.csv';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to hclust.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function hclust_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "hclust";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png','legend.png');
        $data['dir_prefix'] = "rplot";
        $data['blue_disk_extension'] = '.png';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to heatcloud.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function heatcloud_results($job_id,$job_folder,$user_workspace,$input_files){

        $data = array();
        $data2 = array();

        $data['job_id'] = $job_id;
        $data['table_csv'] = url("/storage/get_job_file/job/$job_id/table.csv");
        $data2['content'] = View::make('results/heatcloud',$data);

        $data2['images'] = array();
        $data2['dir_prefix'] = "table";
        $data2['blue_disk_extension'] = '.csv';
        $data2['function'] = "heatcloud";
        $data2['job'] = Job::find($job_id);
        $data2['input_files'] = $input_files;
        $data2['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data2);
        }
    }

    /**
     * Loads job results information related to bict.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function bict_results($job_id,$job_folder,$user_workspace,$input_files){

        $results = "<br>";

        $data = array();
        $data['function'] = "bict";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        } else {
            if(file_exists($job_folder."/indices.txt")){
                $handle = fopen($job_folder."/indices.txt", "r");
                if ($handle) {
                    while (($textline = fgets($handle)) !== false) {
                        $results .= $textline."<br>";
                    }
                    fclose($handle);
                }
            }
        }

        // If the results file exists, then add the contents of this file
        // to the job page
        $data['lines'] = $parser->getOutput();
        $data['lines'][] = $results;

        $data['images'] = array();
        $data['dir_prefix'] = "indices";
        $data['blue_disk_extension'] = '.txt';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }

    }

    /**
     * Loads job results information related to metamds.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function metamds_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "metamds";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png','legend.png');
        $data['dir_prefix'] = "rplot";
        $data['blue_disk_extension'] = '.png';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to second_metamds.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function second_metamds_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "second_metamds";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png','legend.png');
        $data['dir_prefix'] = "dist_2nd_stage";
        $data['blue_disk_extension'] = '.csv';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to pca.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function pca_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "pca";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png','legend.png');
        $data['dir_prefix'] = "rplot";
        $data['blue_disk_extension'] = '.png';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to cca.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function cca_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "cca";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png');
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to regression.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function regression_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "regression";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png','rplot2.png');
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to anosim.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function anosim_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "anosim";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png');
        $data['dir_prefix'] = "rplot";
        $data['blue_disk_extension'] = '.png';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to anova.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function anova_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "anova";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png');
        $data['dir_prefix'] = "rplot";
        $data['blue_disk_extension'] = '.png';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to permanova.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function permanova_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "permanova";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to metamds_visual.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function metamds_visual_results($job_id,$job_folder,$user_workspace,$input_files){

        $data = array();
        $data2 = array();

        $data['job_id'] = $job_id;
        $data['data_js'] = file($job_folder.'/data.js');
        $data2['content'] = View::make('results/metamds_visual',$data);

        $data2['images'] = array();
        $data2['dir_prefix'] = "filtered_abundance";
        $data2['blue_disk_extension'] = '.csv';
        $data2['function'] = "metamds_visual";
        $data2['job'] = Job::find($job_id);
        $data2['input_files'] = $input_files;
        $data2['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data2);
        }
    }

    /**
     * Loads job results information related to cca_visual.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function cca_visual_results($job_id,$job_folder,$user_workspace,$input_files){

        $data = array();
        $data2 = array();

        $data['job_id'] = $job_id;
        $data['data_js'] = file($job_folder.'/dataCCA.js');
        $data2['content'] = View::make('results/cca_visual',$data);

        $data2['images'] = array();
        $data2['dir_prefix'] = "filtered_abundance";
        $data2['blue_disk_extension'] = '.csv';
        $data2['function'] = "cca_visual";
        $data2['job'] = Job::find($job_id);
        $data2['input_files'] = $input_files;
        $data2['job_folder'] = $job_folder;

        if($this->is_mobile){
            return array('data',$data2);
        } else {
            return $this->load_view('results/completed','Job Results',$data2);
        }
    }

    /**
     * Loads job results information related to mapping_tool_visual.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function mapping_tools_visual_results($job_id,$job_folder,$user_workspace,$input_files){

        $data = array();
        $data2 = array();

        $data['job_id'] = $job_id;
        $data['data_js'] = file($job_folder.'/dataMap.js');
        $data2['content'] = View::make('results/mapping_tools_visual',$data);

        $data2['images'] = array();
        $data2['dir_prefix'] = "filtered_abundance";
        $data2['blue_disk_extension'] = '.csv';
        $data2['function'] = "mapping_tools_visual";
        $data2['job'] = Job::find($job_id);
        $data2['input_files'] = $input_files;
        $data2['job_folder'] = $job_folder;

        if($this->is_mobile){
            return array('data',$data2);
        } else {
            return $this->load_view('results/completed','Job Results',$data2);
        }
    }

    /**
     * Loads job results information related to mapping_tools_div_visual.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function mapping_tools_div_visual_results($job_id,$job_folder,$user_workspace,$input_files){

        $data = array();
        $data2 = array();

        $data['job_id'] = $job_id;
        $data['data_js'] = file($job_folder.'/dataMapDiv.js');
        $data2['content'] = View::make('results/mapping_tools_div_visual',$data);

        $data2['images'] = array();
        $data2['dir_prefix'] = "filtered_abundance";
        $data2['blue_disk_extension'] = '.csv';
        $data2['function'] = "mapping_tools_div_visual";
        $data2['job'] = Job::find($job_id);
        $data2['input_files'] = $input_files;
        $data2['job_folder'] = $job_folder;

        if($this->is_mobile){
            return array('data',$data2);
        } else {
            return $this->load_view('results/completed','Job Results',$data2);
        }
    }

    /**
     * Loads job results information related to mantel.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function mantel_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "mantel";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to radfit.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function radfit_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "radfit";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array('rplot.png');
        $data['dir_prefix'] = "rplot";
        $data['blue_disk_extension'] = '.png';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to parallel_taxa2dist.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function parallel_taxa2dist_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "parallel_taxa2dist";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "RvLAB_taxa2Distoutput";
        $data['blue_disk_extension'] = '.csv';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to parallel_postgres_taxa2dist.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function parallel_postgres_taxa2dist_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "parallel_taxa2dist";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "RvLAB_taxa2Distoutput";
        $data['blue_disk_extension'] = '.csv';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to parallel_anosim.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function parallel_anosim_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "parallel_anosim";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to parallel_bioenv.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function parallel_bioenv_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "parallel_bioenv";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return array('data',$data);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to parallel_simper.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function parallel_simper_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "parallel_simper";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return array('data',$data);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to parallel_mantel.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function parallel_mantel_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "parallel_mantel";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to parallel_taxa2taxon.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function parallel_taxa2taxon_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "parallel_taxa2taxon";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array('parallelTaxTaxOnPlot.png');
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to parallel_permanova.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function parallel_permanova_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "parallel_permanova";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/cmd_line_output.txt');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to phylobar.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function phylobar_results($job_id,$job_folder,$user_workspace,$input_files){

        $data = array();
        $data2 = array();

        $data['job_id'] = $job_id;
        $data['table_nwk'] = url("/storage/get_job_file/job/$job_id/table.nwk");
        $data['table_csv'] = url("/storage/get_job_file/job/$job_id/table.csv");
        $data2['content'] = View::make('results/phylobar',$data);

        $data2['images'] = array();
        $data2['dir_prefix'] = "table";
        $data2['blue_disk_extension'] = '.csv';
        $data2['function'] = "phylobar";
        $data2['job'] = Job::find($job_id);
        $data2['input_files'] = $input_files;
        $data2['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data2);
        }
    }

    /**
     * Loads job results information related to bioenv.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function bioenv_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "bioenv";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to simper.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function simper_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "simper";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "";
        $data['blue_disk_extension'] = '';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

    /**
     * Loads job results information related to convert2r.
     *
     * @param type $job_id
     * @param type $job_folder
     * @param type $user_workspace
     * @param type $input_files
     * @return View|JSON
     */
    private function convert2r_results($job_id,$job_folder,$user_workspace,$input_files){
        $data = array();
        $data['function'] = "convert2r";
        $data['job'] = Job::find($job_id);
        $data['input_files'] = $input_files;

        $parser = new RvlabParser();
        $parser->parse_output($job_folder.'/job'.$job_id.'.Rout');
        if($parser->hasFailed()){
            $data['errorString'] = $parser->getOutput();
            if($this->is_mobile){
                return array('data',$data);
            } else {
                return $this->load_view('results/failed','Job Results',$data);
            }
        }

        $data['lines'] = $parser->getOutput();
        $data['images'] = array();
        $data['dir_prefix'] = "transformed_dataAbu";
        $data['blue_disk_extension'] = '.csv';
        $data['job_folder'] = $job_folder;

        if($this->is_mobile){
            return Response::json(array('data',$data),200);
        } else {
            return $this->load_view('results/completed','Job Results',$data);
        }
    }

}
