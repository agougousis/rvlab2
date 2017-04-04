<?php

namespace App\Exceptions;

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

    public function setErrorsToReturn($errors)
    {
        $this->errorsToReturn = $errors;
    }

    public function getErrorsToReturn()
    {
        return $this->errorsToReturn;
    }
}
