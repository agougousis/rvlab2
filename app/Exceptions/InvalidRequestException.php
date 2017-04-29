<?php

namespace App\Exceptions;

use Session;
use Response;
use Redirect;

/**
 * An exception that happens when a user takes an action providing invalid
 * parameters.
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class InvalidRequestException extends CustomException
{
    private $errorsToReturn = null;

    public function __construct($message = "", $defaultHttpCode = 400, $code = 0, \Exception $previous = null)
    {
        parent::__construct($defaultHttpCode, $message, $code, $previous);
    }

    /**
     * Setter
     *
     * @param array $errors
     */
    public function setErrorsToReturn($errors)
    {
        $this->errorsToReturn = $errors;
    }

    /**
     * Getter
     *
     * @return array
     */
    public function getErrorsToReturn()
    {
        return $this->errorsToReturn;
    }

    /**
     * Logs error and generates an appropriate response
     *
     * @return Response|Redirect
     */
    public function logAndRespond()
    {
        $this->logEvent($this->getMessage(), "invalid");

        if ($this->toastr) {
            Session::flash('toastr', array('error', $this->userMessage));
        }

        if ($this->isMobileRequest) {
            $response = array('message', $this->userMessage);
            return Response::json($response, $this->httpCode);
        } else {
            if (!empty($this->errorsToReturn)) {
                return Redirect::back()->withInput()->withErrors($this->errorsToReturn);
            }

            return Redirect::back();
        }
    }
}
