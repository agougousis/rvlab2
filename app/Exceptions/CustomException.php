<?php

namespace App\Exceptions;

abstract class CustomException extends \Exception
{
    private $userMessage;
    private $toastr = false;
    private $httpCode;
    private $isMobileRequest = false;

    public function __construct($defaultHttpCode, $message = "", $code = 0, \Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);

        $this->httpCode = $defaultHttpCode;
        $this->isMobileRequest = is_mobile();
    }

    public function setUserMessage($message)
    {
        $this->userMessage = $message;
    }

    public function getUserMessage()
    {
        return $this->userMessage;
    }

    public function getLogMessage()
    {
        return $this->getMessage();
    }

    public function enableToastr()
    {
        $this->toastr = true;
    }

    public function displayToastr()
    {
        return $this->toastr;
    }

    public function proposedHttpCode($code)
    {
        $this->httpCode = $code;
    }

    public function getHttpCode()
    {
        return $this->httpCode;
    }

    public function isMobileRequest()
    {
        return $this->isMobileRequest;
    }
}
