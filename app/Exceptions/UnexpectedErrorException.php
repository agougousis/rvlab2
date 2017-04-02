<?php

namespace App\Exceptions;

/**
 * An exception representing unexpected server-side errors
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class UnexpectedErrorException extends CustomException
{
    public function __construct($message = "", $defaultHttpCode = 500, $code = 0, \Exception $previous = null) {
        parent::__construct($defaultHttpCode, $message, $code, $previous);
    }
}
