<?php

namespace App\Exceptions;

use Session;
use Response;
use Redirect;

/**
 * An exception representing unexpected server-side errors
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class UnexpectedErrorException extends CustomException
{
    public function __construct($message = "", $defaultHttpCode = 500, $code = 0, \Exception $previous = null)
    {
        parent::__construct($defaultHttpCode, $message, $code, $previous);
    }

    /**
     * Logs error and generates an appropriate response
     *
     * @return Response|Redirect
     */
    public function logAndRespond()
    {
        $this->logEvent($this->getMessage(), "illegal");

        if ($this->isMobileRequest) {
            $response = array('message', $this->userMessage);
            return Response::json($response, $this->httpCode);
        } else {
            Session::flash('toastr', array('error', $this->userMessage));
            return Redirect::back();
        }
    }
}
