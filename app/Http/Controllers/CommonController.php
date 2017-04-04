<?php

namespace App\Http\Controllers;

use Response;
use Redirect;
use Session;
use App\Models\Setting;
use App\Models\SystemLog;
use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Route;

/**
 * Builds functionality related to access control and logging
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class CommonController extends Controller
{
    protected $system_settings = array();
    protected $is_mobile;

    public function __construct()
    {
        // Identify if the request comes from a mobile client
        $this->is_mobile = is_mobile();

        $settings = Setting::all();
        foreach ($settings as $set) {
            $this->system_settings[$set->sname] = $set->value;
        }
    }

    /**
     * Provides a CSRF token to mobile application. Since the mobile app submits
     * forms to the same URLs as the web app, it needs to include a CSRF token as well.
     *
     * @return JSON
     */
    public function getToken()
    {
        $token = csrf_token();
        $response = array(
            'token' => $token,
            'when' => date('Y-m-d H:i:s')
        );
        return Response::json($response, 200);
    }

    /**
     * Saves a log to the database
     *
     * @param string $message
     * @param string $category
     */
    protected function logEvent($message, $category)
    {

        $db_message = $message;
        $route = explode('@', Route::currentRouteName());

        $log = new SystemLog();
        $log->when = date("Y-m-d H:i:s");
        $log->user_email = session('user_info.email');
        $log->controller = (!empty($route[0])) ? $route[0] : 'unknown';
        $log->method = (!empty($route[0])) ? $route[1] : 'unknown';
        $log->message = $db_message;
        $log->category = $category;
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
    protected function loadView($the_view, $title, $data = array())
    {
        $userInfo = session('user_info');

        $content = view($the_view, $data);

        $page = view('template')
                ->with('title', $title)
                ->with('head', $userInfo['head'])
                ->with('body_top', $userInfo['body_top'])
                ->with('body_bottom', $userInfo['body_bottom'])
                ->with('content', $content);

        $response = Response::make($page);
        return $response->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-stale=0, post-check=0, pre-check=0');
    }

    /**
     * Produces a response for an illegal action
     *
     * @param string $errorMessage
     * @param int $errorStatus
     * @return Response
     */
    protected function illegalActionResponse($errorMessage, $errorStatus)
    {
        $this->logEvent($errorMessage, "error");
        if ($this->is_mobile) {
            $response = array('message', $errorMessage);
            return Response::json($response, $errorStatus);
        } else {
            return $this->illegalAction();
        }
    }

    /**
     * Produces a response for a server-side unexpected error
     *
     * @param string $logMessage
     * @param string $userMessage
     * @return RedirectResponse
     */
    protected function unexpectedErrorResponse($logMessage, $userMessage)
    {
        $this->logEvent($logMessage, "error");
        if ($this->is_mobile) {
            $response = array('message', $userMessage);
            return Response::json($response, 500);
        } else {
            Session::flash('toastr', array('error', $userMessage));
            return Redirect::back();
        }
    }

    /**
     * Produces a typical response for successful requests
     *
     * @return Response|RedirectResponse
     */
    protected function okResponse($toastr = null)
    {
        if ($this->is_mobile) {
            return Response::json(array(), 200);
        } else {
            if (!empty($toastr)) {
                Session::flash('toastr', array('success', $toastr));
            }
            return Redirect::to('/');
        }
    }

    /**
     * Displays a page with a message about an iilegal request.
     *
     * @return View
     */
    protected function illegalAction()
    {
        return $this->loadView('errors/illegalAction', 'Unexpected error');
    }

    /**
     * Checks if the remote (cluster) storage has been mounted
     *
     * @return boolean
     */
    protected function checkStorage()
    {
        $jobs_path = config('rvlab.jobs_path');
        if (!file_exists($jobs_path)) {
            return false;
        } else {
            return true;
        }
    }
}
