<?php

namespace App\Exceptions;

use Route;
use Session;
use Exception;
use Response;
use Redirect;
use App\Models\SystemLog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Exceptions\Handler as ExceptionHandler;

class Handler extends ExceptionHandler
{
    /**
     * A list of the exception types that should not be reported.
     *
     * @var array
     */
    protected $dontReport = [
        \Illuminate\Auth\AuthenticationException::class,
        \Illuminate\Auth\Access\AuthorizationException::class,
        \Symfony\Component\HttpKernel\Exception\HttpException::class,
        \Illuminate\Database\Eloquent\ModelNotFoundException::class,
        \Illuminate\Session\TokenMismatchException::class,
        \Illuminate\Validation\ValidationException::class,
    ];

    /**
     * Report or log an exception.
     *
     * This is a great spot to send exceptions to Sentry, Bugsnag, etc.
     *
     * @param  \Exception  $exception
     * @return void
     */
    public function report(Exception $exception)
    {
        parent::report($exception);
    }

    /**
     * Render an exception into an HTTP response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Exception  $exception
     * @return \Illuminate\Http\Response
     */
    public function render($request, Exception $exception)
    {
        // Handle StorageNotFoundException
        if ($exception instanceof StorageNotFoundException) {
            $this->logEvent($exception->getLogMessage(), "storage");

            if ($exception->isMobileRequest()) {
                $response = array('message', $exception->getUserMessage());
                return Response::json($response, $exception->getHttpCode());
            } else {
                return $this->loadView('errors/unmounted', 'Storage not found');
            }
        }

        // Handle UnexpectedErrorException
        if ($exception instanceof UnexpectedErrorException) {
            $this->logEvent($exception->getLogMessage(), "illegal");

            if ($exception->isMobileRequest()) {
                $response = array('message', $exception->getUserMessage());
                return Response::json($response, $exception->getHttpCode());
            } else {
                Session::flash('toastr', array('error', $exception->getUserMessage()));
                return Redirect::back();
            }
        }

        // Handle AuthorizationException
        if ($exception instanceof AuthorizationException) {
            $this->logEvent($exception->getLogMessage(), "unauthorized");

            if ($exception->displayToastr()) {
                Session::flash('toastr', array('error', $exception->getUserMessage()));
            }

            $response = array('message', $exception->getUserMessage());
            return Response::json($response, $exception->getHttpCode());
        }

        // Handle InvalidRequestException
        if ($exception instanceof InvalidRequestException) {
            $this->logEvent($exception->getLogMessage(), "invalid");

            if ($exception->displayToastr()) {
                Session::flash('toastr', array('error', $exception->getUserMessage()));
            }

            if ($exception->isMobileRequest()) {
                $response = array('message', $exception->getUserMessage());
                return Response::json($response, $exception->getHttpCode());
            } else {
                if (!empty($errors = $exception->getErrorsToReturn())) {
                    return Redirect::back()->withInput()->withErrors($errors);
                }

                return Redirect::back();
            }
        }

        return parent::render($request, $exception);
    }

    /**
     * Convert an authentication exception into an unauthenticated response.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Illuminate\Auth\AuthenticationException  $exception
     * @return \Illuminate\Http\Response
     */
    protected function unauthenticated($request, AuthenticationException $exception)
    {
        if ($request->expectsJson()) {
            return response()->json(['error' => 'Unauthenticated.'], 401);
        }

        return redirect()->guest('login');
    }

    /**
     * Saves a log to the database
     *
     * @param string $message
     * @param string $category
     */
    protected function logEvent($message, $category)
    {
        $db_message = $message;
        $route = explode('@', Route::currentRouteName());

        $log = new SystemLog();
        $log->when = date("Y-m-d H:i:s");
        $log->user_email = session('user_info.email');
        $log->controller = (!empty($route[0])) ? $route[0] : 'unknown';
        $log->method = (!empty($route[0])) ? $route[1] : 'unknown';
        $log->message = $db_message;
        $log->category = $category;
        $log->save();
    }

    /**
     * Loads a View using a template file and the HTML wrapper parts provided by the portal.
     *
     * @param string $the_view
     * @param string $title
     * @param array $data
     * @return View
     */
    protected function loadView($the_view, $title, $data = array())
    {
        $userInfo = session('user_info');

        $content = view($the_view, $data);

        $page = view('template')
                ->with('title', $title)
                ->with('head', $userInfo['head'])
                ->with('body_top', $userInfo['body_top'])
                ->with('body_bottom', $userInfo['body_bottom'])
                ->with('content', $content);

        $response = Response::make($page);
        return $response->header('Cache-Control', 'no-cache, no-store, must-revalidate, max-stale=0, post-check=0, pre-check=0');
    }
}
