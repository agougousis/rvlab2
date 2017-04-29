<?php

namespace App\Http\Middleware;

use Closure;
use Response;
use Redirect;
use App\Models\Registration;

/**
 * Checks if the user has an active R vLab registration and if not, it redirects him
 * to login page (if his is not logged in) or registration page (if he has not registered).
 * (this should be called only by routes that requires a logged in user)
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class IsRegistered
{
    /**
     * The URIs that should be excluded from registration check
     *
     * @var array
     */
    protected $except = [
        'rlogin',
        'registration'
    ];

    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if ($this->shouldPassThrough($request)) {
            return $next($request);
        }

        if (empty(session('user_info'))){
            // Just in case the user is not logged in (should not happen normally)
            if(!request()->ajax()){
                $response = array('message', 'Unknown user!');
                return Response::json($response, 500);
            }
        } else {
            // Check if has an active registration
            $registration = Registration::where('user_email', session('user_info.email'))->where('ends', '>', date('Y-m-d H:i:s'))->get()->toArray();
            if(empty($registration)){
                if (is_mobile()) {
                    $response = array('message', 'You are not registered!');
                    return Response::json($response, 401);
                } else {
                    return Redirect::to('registration');
                }
            }
        }

        return $next($request);
    }

    /**
     * Determine if the request has a URI that should pass through CSRF verification.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return bool
     */
    protected function shouldPassThrough($request)
    {
        foreach ($this->except as $except) {
            if ($except !== '/') {
                $except = trim($except, '/');
            }

            if ($request->is($except)) {
                return true;
            }
        }

        return false;
    }
}
