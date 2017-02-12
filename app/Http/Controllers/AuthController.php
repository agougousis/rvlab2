<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Route;
use Response;
use App\Models\Setting;
use App\Models\SystemLog;
use App\Models\Registration;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Builds functionality related to access control and logging
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class AuthController extends Controller {

    protected $userInfo;
    protected $system_settings = array();
    protected $is_mobile;
    private $registration;
    private $portal_home_url = "https://portal.lifewatchgreece.eu";

    public function __construct()
    {
        // Identify if the request comes from a mobile client
        if(isset($_SERVER['HTTP_AAAA1']))
            $this->is_mobile = true;
        else
            $this->is_mobile = false;

        $settings = Setting::all();
        foreach($settings as $set){
            $this->system_settings[$set->sname] = $set->value;
        }
    }

    /**
     * Checks if the user has an active R vLab registration.
     *
     * @return boolean
     */
    protected function isRegistered(){
        $userInfo = session('user_info');
        if (empty($userInfo)){ // This case should not ever happen
            return false;
        } else {
            // Check if has an active registration
            $registration = Registration::where('user_email',$userInfo['email'])->where('ends','>',date('Y-m-d H:i:s'))->get()->toArray();
            if(empty($registration)){
                return false;
            } else {
                return true;
            }
        }
    }

    /**
     * Provides a CSRF token to mobile application. Since the mobile app submits
     * forms to the same URLs as the web app, it needs to include a CSRF token as well.
     *
     * @return JSON
     */
    public function get_token(){
        $token = csrf_token();
        $response = array(
            'token' =>  $token,
            'when'  =>  date('Y-m-d H:i:s')
        );
        return Response::json($response,200);
    }



    /**
     * Saves a log to the database
     *
     * @param string $message
     * @param string $category
     */
    protected function log_event($message,$category){

        $db_message = $message;
        $route = explode('@',Route::currentRouteName());

//        if (!empty($this->user_status)){
//            if(!empty($this->user_status['email'])){
//                $user_id = $this->user_status['email'];
//            } else {
//                $user_id = 'visitor';
//            }
//        } else {
//            $user_id = '';
//            $category = 'error';
//            $db_message = 'User status could not be retrieved during logging action. Original message was: '.$message;
//        }

	$log = new SystemLog();
	$log->when 	=   date("Y-m-d H:i:s");
	$log->user_email =   '';
	$log->controller =  (!empty($route[0]))? $route[0] : 'unknown';
	$log->method 	=   (!empty($route[0]))? $route[1] : 'unknown';
	$log->message 	=   $db_message;
        $log->category   =   $category;
	$log->save();
    }

    /**
     * Loads a View using a template file and the HTML wrapper parts provided by the portal.
     *
     * @param string $the_view
     * @param string $title
     * @param array $data
     * @return View
     */
    protected function load_view($the_view,$title,$data = array()){
        $userInfo = session('user_info');

        $head = $userInfo['head'];
        $body_top = $userInfo['body_top'];
        $body_bottom = $userInfo['body_bottom'];
        $content = view($the_view,$data);

        $page = view('template')
                ->with('title',$title)
                ->with('head',$head)
                ->with('body_top',$body_top)
                ->with('body_bottom',$body_bottom)
                ->with('content',$content);

        $response = Response::make($page);
        return $response->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-stale=0, post-check=0, pre-check=0');
    }

    /**
     * Displays a page with a message about unauthorized action
     *
     * @return View
     */
    protected function unauthorizedAccess(){

        return $this->load_view('errors/unauthorized','Unauthorized access');

    }

    /**
     * Displays a page with a message about unauthorized action
     *
     * @return View
     */
    protected function illegalAction(){

        return $this->load_view('errors/illegalAction','Illegal action');

    }

    /**
     * Displays a page with a message about unexpected error.
     *
     * @return View
     */
    protected function unexpected_error(){

        return $this->load_view('errors/unexpected','Unexpected error');

    }

    /**
     * Checks if the remote (cluster) storage has been mounted
     *
     * @return boolean
     */
    protected function check_storage(){
        $jobs_path = config('rvlab.jobs_path');
        if(!file_exists($jobs_path)){
            return false;
        } else {
            return true;
        }
    }

}

