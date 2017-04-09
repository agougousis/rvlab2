<?php

namespace App\Exceptions;

/**
 * An exception that happens when the remote/cluster storage is not mounted
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class StorageNotFoundException extends CustomException
{
    public function __construct($message = "", $defaultHttpCode = 500, $code = 0, \Exception $previous = null)
    {
        parent::__construct($defaultHttpCode, $message, $code, $previous);
    }
}
