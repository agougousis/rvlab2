<?php

namespace App\Http\Middleware;

use Redirect;
use Closure;
use App\Models\Registration;
use App\Contracts\Authenticator;
use Illuminate\Http\Request;

/**
 * Implements the authorization rules
 *
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class AuthorizationLayer
{
    private $authenticator;

    public function __construct(Authenticator $authenticator)
    {
      $this->authenticator = $authenticator;
    }

    /**
     * Handle authentication/authorization.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {
        if (session('user_status') == 'visitor') {
            if($request->ajax()){
                return "";
            }

            if ($request->fullUrl() != $this->authenticator->getLoginUrl()) {
                return redirect()->away($this->authenticator->getLoginUrl(), 302);
            }

            return $next($request);
        }

        // Check if he has registered to R vLab
        if (!$this->is_registered_to_rvlab($request)) {
            if ($request->path() != 'registration') {
                return redirect('registration');
            }
        }

        return $next($request);
    }

    /**
     * Checks if the user has an active R vLab registration and if not, it redirects him
     * to login page (if his is not logged in) or registration page (if he has not registered).
     * (this should be called only by routes that requires a logged in user)
     *
     * @return Redirect|void
     */
    protected function is_registered_to_rvlab(Request $request){
        if (!session()->has('user_info')){
            // Just in case the user is not logged in (should not happen normally)
            if(!$request->ajax()){
                return Redirect::to($this->authenticator->getloginUrl());
            }
        } else {
            $userInfo = session('user_info');

            // Check if has an active registration
            $now = (new \DateTime)->format('Y-m-d H:i:s');
            $registration = Registration::where('user_email',$userInfo['email'])->where('ends','>',$now)->get()->toArray();

            if(empty($registration)){
                return false;
            }

            return true;
        }
    }
}
