<?php

namespace App\Exceptions;

use Session;
use Response;

/**
 * An exception that happens when a user attemps something he is not authorized
 * to.
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class AuthorizationException extends CustomException
{
    public function __construct($message = "", $defaultHttpCode = 401, $code = 0, \Exception $previous = null)
    {
        parent::__construct($defaultHttpCode, $message, $code, $previous);
    }

    /**
     * Logs error and generates an appropriate response
     *
     * @return Response
     */
    public function logAndRespond()
    {
        $this->logEvent($this->getMessage(), "unauthorized");

        if ($this->toastr) {
            Session::flash('toastr', array('error', $this->userMessage));
        }

        $response = array('message', $this->userMessage);
        return Response::json($response, $this->httpCode);
    }
}
