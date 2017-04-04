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
    /**
     * Holds the authenticator instance which defines the authorization
     * procedure
     *
     * @var App\Contracts\Authenticator
     */
    private $authenticator;

    /**
     * A list or URLs that are accessible by non logged in users
     *
     * The URLs are expressed in their short form as defined in Laravel routes
     *
     * @var array
     */
    private $urlsAllowedToVisitors = [];

    /**
     * A list or URLs that are accessible by unregistered users
     *
     * @var array
     */
    private $urlsAllowedToUnregistered = [];

    /**
     * A list or URLs that are forbidden to registered users
     *
     * @var array
     */
    private $urlsForbiddenToRegistered = [];

    public function __construct(Authenticator $authenticator)
    {
        $this->authenticator = $authenticator;

        $this->urlsAllowedToUnregistered[] = url('registration');

        $this->urlsAllowedToVisitors[] = $this->authenticator->getLoginUrl();

        $this->urlsForbiddenToRegistered[] = url('registration');
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
        // The user has not logged in
        if (session('user_status') == 'visitor') {
            if ($request->ajax()) {
                return "";
            }

            // Users that are not logged in can access only specific URLs
            if (!in_array($request->fullUrl(), $this->urlsAllowedToVisitors)) {
                return $this->redirectOrMobile($this->authenticator->getLoginUrl(), 403, 'You need to login first!');
            }

            return $next($request);
        }

        // The user is logged in
        if (!$this->isRregisteredToRvlab($request)) {
            // Unregistered users can access only specific URLs
            if (!in_array($request->fullUrl(), $this->urlsAllowedToUnregistered)) {
                return $this->redirectOrMobile('registration', 403, 'You are not registered to R vLab!');
            }
        } else {
            // Registered users cannot access specific URLs
            if (in_array($request->fullUrl(), $this->urlsForbiddenToRegistered)) {
                return $this->redirectOrMobile('/', 403, 'You are already registered to R vLab!');
            }
        }

        return $next($request);
    }

    /**
     * Creates an appropriate response depending on wether the user is a mobile
     * one or not
     *
     * @param string $url
     * @param int $status_code
     * @param string $message
     * @return mixed
     */
    protected function redirectOrMobile($url, $status_code, $message)
    {
        if (is_mobile()) {
            abort($status_code, $message);
        } else {
            if (preg_match('/http(.*)/', $url)) {
                return redirect()->away($url, 302);
            } else {
                return redirect($url, 302);
            }
        }
    }

    /**
     * Checks if the user has an active R vLab registration and if not, it redirects him
     * to login page (if his is not logged in) or registration page (if he has not registered).
     * (this should be called only by routes that requires a logged in user)
     *
     * @return Redirect|void
     */
    protected function isRregisteredToRvlab(Request $request)
    {
        if (!session()->has('user_info')) {
            // Just in case the user is not logged in (should not happen normally)
            return false;
        } else {
            $userInfo = session('user_info');

            // Check if has an active registration
            $now = (new \DateTime)->format('Y-m-d H:i:s');
            $registration = Registration::where('user_email', $userInfo['email'])->where('ends', '>', $now)->get()->toArray();

            if (empty($registration)) {
                return false;
            }

            return true;
        }
    }
}
