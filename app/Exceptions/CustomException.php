<?php

namespace App\Exceptions;

use Route;
use App\Models\SystemLog;

/**
 * A base class for defining custom exceptions
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
abstract class CustomException extends \Exception
{
    /**
     * A message to be displayed to the user
     *
     * @var string
     */
    protected $userMessage;

    /**
     * Indicates whether a toastr message should be displayed to the user.
     *
     * @var boolean
     */
    protected $toastr = false;

    /**
     * The HTTP status code to be used for the response
     * @var type
     */
    protected $httpCode;

    /**
     * Indicates if the request comes from the mobile version of R vLab
     *
     * @var boolean
     */
    protected $isMobileRequest = false;

    public function __construct($defaultHttpCode, $message = "", $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->httpCode = $defaultHttpCode;
        $this->isMobileRequest = is_mobile();
    }

    /**
     * Setter
     *
     * @param string $message
     */
    public function setUserMessage($message)
    {
        $this->userMessage = $message;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getUserMessage()
    {
        return $this->userMessage;
    }

    /**
     * Getter
     *
     * @return string
     */
    public function getLogMessage()
    {
        return $this->getMessage();
    }

    /**
     * Setter
     */
    public function enableToastr()
    {
        $this->toastr = true;
    }

    /**
     * Getter
     *
     * @return boolean
     */
    public function displayToastr()
    {
        return $this->toastr;
    }

    /**
     * Setter
     *
     * @param int $code
     */
    public function proposedHttpCode($code)
    {
        $this->httpCode = $code;
    }

    /**
     * Getter
     *
     * @return type
     */
    public function getHttpCode()
    {
        return $this->httpCode;
    }

    /**
     * Getter
     *
     * @return boolean
     */
    public function isMobileRequest()
    {
        return $this->isMobileRequest;
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
}
