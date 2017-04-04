<?php

namespace App\Http\Middleware;

use Closure;
use App\Contracts\Authenticator;

/**
 * Handles the authentication procedure
 *
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class AuthenticationLayer
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
        // Retrieve user status
        $authResult = $this->authenticator->authenticate();

        // Save user info to session
        session([
            'user_status' => $authResult['status'],
            'user_info' =>  $authResult['info'],
            'is_admin'  =>  $authResult['is_admin']
        ]);

        return $next($request);
    }
}
