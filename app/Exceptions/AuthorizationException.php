<?php

namespace App\Exceptions;

/**
 * An exception that happens when a user attemps something he is not authorized
 * to.
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class AuthorizationException extends CustomException
{
    public function __construct($message = "", $defaultHttpCode = 401, $code = 0, \Exception $previous = null) {
        parent::__construct($defaultHttpCode, $message, $code, $previous);
    }
}
