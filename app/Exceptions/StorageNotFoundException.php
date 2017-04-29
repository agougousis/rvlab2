<?php

namespace App\Exceptions;

use Response;

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

    /**
     * Logs error and generates an appropriate response
     *
     * @return Response|View
     */
    public function logAndRespond()
    {
        $this->logEvent($this->getMessage(), "storage");

        if ($this->isMobileRequest) {
            $response = array('message', $this->userMessage);
            return Response::json($response, $this->httpCode);
        } else {
            return $this->loadView('errors/unmounted', 'Storage not found');
        }
    }
}
