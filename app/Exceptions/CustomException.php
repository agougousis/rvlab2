<?php

namespace App\Exceptions;

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
    private $userMessage;

    /**
     * Indicates whether a toastr message should be displayed to the user.
     *
     * @var boolean
     */
    private $toastr = false;

    /**
     * The HTTP status code to be used for the response
     * @var type
     */
    private $httpCode;

    /**
     * Indicates if the request comes from the mobile version of R vLab
     *
     * @var boolean
     */
    private $isMobileRequest = false;

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
}
