<?php

namespace App\Http\Controllers;

use DB;
use Input;
use DateTime;
use Redirect;
use App\Models\Job;
use App\Models\JobsLog;
use App\Models\Setting;
use App\Models\SystemLog;
use App\Models\Registration;
use App\Models\WorkspaceFile;
use App\Http\Controllers\AuthController;
use Illuminate\Http\Request;
use Illuminate\Foundation\Application;
use phpseclib\Net\SSH2;

/**
 * Handles the administration functionality of R vLab
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class AdminController extends AuthController {

    /**
     * Loads the administration control panel
     *
     * @return View
     */
    public function index(){
        return $this->load_view('admin.index','Admin Pages');
    }

    /**
     * Loads the system configuration page
     *
     * @return View
     */
    public function configure(){

        $settings = Setting::all();
        $data['settings'] = $settings;

        return $this->load_view('admin.configure','System Configuration',$data);

    }

    /**
     * Saves the new system configuration
     *
     * @return RedirectResponse
     */
    public function save_configuration(Request $request){
        $form = $request->all();
        if(!empty($form)){
            foreach($form as $key => $value){
                $setting = Setting::where('sname',$key)->first();
                if(!empty($setting)){
                    $setting->value = $value;
                    $setting->last_modified = (new DateTime())->format("Y-m-d H:i:s");
                    $setting->save();
                }
            }
        }

        return Redirect::to('admin/configure');
    }

    /**
     * Loads R vLab usage statistics page
     *
     * @return View
     */
    public function statistics(){

        /******************************
         *  Registration Statistics
         ******************************/
        $limits = array(
            array('Jan','01-01 00:00:00','01-15 23:59:59'),
            array('Feb','02-01 00:00:00','02-15 23:59:59'),
            array('Mar','03-01 00:00:00','03-15 23:59:59'),
            array('Apr','04-01 00:00:00','04-15 23:59:59'),
            array('May','05-01 00:00:00','05-15 23:59:59'),
            array('Jun','06-01 00:00:00','06-15 23:59:59'),
            array('Jul','07-01 00:00:00','07-15 23:59:59'),
            array('Aug','08-01 00:00:00','08-15 23:59:59'),
            array('Sep','09-01 00:00:00','09-15 23:59:59'),
            array('Oct','10-01 00:00:00','10-15 23:59:59'),
            array('Nov','11-01 00:00:00','11-15 23:59:59'),
            array('Dec','12-01 00:00:00','12-15 23:59:59'),
        );

        $counts = array();

        $year = date('Y');
        $prev_year = $year - 1;
        $month = date('n');
        $day = date('j');
        if ($day < 15)
            $month--;

        // From last year's same month to last year's end
        for($j = $month; $j < 12; $j++){
            // Get the middle day of this months
            $checkpoint = $prev_year."-".$limits[$j][2];
            // Get registrations that were active during this day
            $regs = Registration::where('starts','<=',$checkpoint)
                    ->where('ends','>=',$checkpoint)
                    ->count();
            // Store month label alogside the counted registrations
            $counts[] = array($limits[$j][0]." ".$prev_year, $regs);
        }

        // From this year's start to previous month
        for($j = 0; $j < $month; $j++){
            // Get the middle day of this months
            $checkpoint = $year."-".$limits[$j][2];
            // Get registrations that were active during this day
            $regs = Registration::where('starts','<=',$checkpoint)
                    ->where('ends','>=',$checkpoint)
                    ->count();
            // Store month label alogside the counted registrations
            $counts[] = array($limits[$j][0]." ".$year, $regs);
        }

        /******************************
         *  Functions Statistics
         ******************************/

        $dateLimit = "$prev_year-$month-$day 00:00:00";

        $f_stats = JobsLog::select(DB::raw('count(*) as total,function'))
                ->where('submitted_at','>',$dateLimit)
                ->groupBy('function')
                ->get()->toArray();

        /******************************
         *  Job size Statistics
         ******************************/

        $s_stats = array(
            '1' =>  0,
            '2' =>  0,
            '3' =>  0,
            '4' =>  0,
            '5' =>  0,
            '6' =>  0,
            '7' =>  0,
            '8' =>  0,
            '9' =>  0
        );

        // We just count how many jobs are there with jobsize of x digits
        $size_stats = JobsLog::select(DB::raw('count(*) as total, LENGTH(jobsize) AS digits'))
                ->where('submitted_at','>',$dateLimit)
                ->groupBy('digits')
                ->get()->toArray();

        foreach($size_stats as $stat){
            $s_stats[$stat['digits']] = $stat['total'];
        }

        $data['registration_counts'] = $counts;
        $data['f_stats'] = $f_stats;
        $data['s_stats'] = $s_stats;
        return $this->load_view('admin.statistics','R vLab Usage Statistics',$data);

    }

    /**
     * Displays the last 50 jobs submitted to R vLab
     *
     * @return View
     */
    public function job_list(){

        $job_list = Job::take(50)->orderBy('submitted_at','desc')->get();
        $data['job_list'] = $job_list;
        return $this->load_view('admin.job_list','Last Jobs List',$data);

    }

    /**
     * Displays the last 20 errors logged by R vLab
     *
     * @return View
     */
    public function last_errors(){

        $error_list = SystemLog::where('category','error')->orderBy('when','desc')->take(20)->get();
        $data['error_list'] = $error_list;
        return $this->load_view('admin.last_errors','Last errors list',$data);

    }

    /**
     * Displays storate utilization information for each R vLab user.
     *
     * @return View
     */
    public function storage_utilization(){

        $rvlab_storage_limit = $this->system_settings['rvlab_storage_limit'];
        $max_users_supported = $this->system_settings['max_users_supported'];
        $jobs_path = config('rvlab.jobs_path');
        $workspace_path = config('rvlab.workspace_path');

        // Total Storage Utilization
        $jobs_size = directory_size($jobs_path); // in KB
        $workspace_size = directory_size($workspace_path); // in KB
        $used_size = $jobs_size+$workspace_size;
        $utilization = 100*$used_size/$rvlab_storage_limit;

        // Storage Utilization per User (A - input files)
        $inputs_users = WorkspaceFile::select('user_email')->distinct()->get(); // Get users with a least one input file

        $user_totals = array();
        $inputs_totals = array();

        foreach($inputs_users as $user){
            $inputs_totals[$user->user_email] = directory_size($workspace_path.'/'.$user->user_email); // in KB
            $user_totals[$user->user_email] =  $inputs_totals[$user->user_email];
        }

        // Storage Utilization per User (B - jobs)
        $rvlab_users = Job::select('user_email')->distinct()->get(); // Get users with a least one job
        $jobspace_totals = array();
        foreach($rvlab_users as $user){
            $jobspace_totals[$user->user_email] = directory_size($jobs_path.'/'.$user->user_email); // in KB
            if(isset($user_totals[$user->user_email])){
                $user_totals[$user->user_email] += $jobspace_totals[$user->user_email];
            } else {
                $user_totals[$user->user_email] = $jobspace_totals[$user->user_email];
            }
        }

        // Calculating numbers and strings (related to user utilization) that
        // will be used in view
        $user_soft_limit = $rvlab_storage_limit / $max_users_supported; // in KB

        if($used_size > 1000000)
            $utilized_text = number_format($used_size/1000000,2)." GB";
        elseif($used_size > 1000)
            $utilized_text = number_format($used_size/1000,2)." MB";
        else
            $utilized_text = number_format($used_size,2)." KB";

        $new_user_totals = [];
        foreach ($user_totals as $email => $size_number) {
            $sizeInfo = [];

            $progress = number_format(100*$size_number/$user_soft_limit,1);
            if($size_number > 1000000){
                $size_text = number_format($size_number/1000000,2)." GB";
            } elseif($size_number > 1000) {
                $size_text = number_format($size_number/1000,2)." MB";
            } else {
                $size_text = number_format($size_number,2)." KB";
            }

            $sizeInfo['size_number'] = $size_number;
            $sizeInfo['size_text'] = $size_text;
            $sizeInfo['progress'] = $progress;

            $new_user_totals[$email] = $sizeInfo;
        }

        // Note: $inputs_totals and $jobspace_totals are not used for the moment.
        // If they are not going to be used in the future, we don't need to keep them
        // in separate variables.

        $data['rvlab_storage_limit'] = $rvlab_storage_limit;
        $data['max_users_supported'] = $max_users_supported;
        $data['user_totals'] = $new_user_totals;
        $data['utilized_text'] = $utilized_text;
        $data['utilization'] = $utilization;
        return $this->load_view('admin.storage_utilization','Storage Utilization',$data);

    }

}
