<?php

namespace App\Http\Middleware;

use Closure;

/**
 * Forbids access to non-admin users
 *
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class AdminOnly
{
    public function handle($request, Closure $next)
    {
        if (!session('is_admin')) {
            return redirect('/');
        }

        return $next($request);
    }
}
