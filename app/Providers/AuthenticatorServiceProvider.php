<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

/**
 * Description of AuthenticatorServiceProvider
 *
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class AuthenticatorServiceProvider extends ServiceProvider {
    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->app->bind(
            'App\Contracts\Authenticator',
            'App\Authenticators\LocalAuthenticator'
        );
    }
}
