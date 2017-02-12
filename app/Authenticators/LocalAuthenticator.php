<?php

namespace App\Authenticators;

use Session;
use App\Contracts\Authenticator;

/**
 * A default dummy Authenticator
 *
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class LocalAuthenticator implements Authenticator {

    /**
     * The URL to redirect users who are not logged in
     *
     * @var string
     */
    private $login_url;

    public function __construct()
    {
        $this->login_url = url('rlogin');
    }

    /**
     * Returns the URL where visitors should be redirected to in order to login.
     */
    public function getLoginUrl()
    {
        return $this->login_url;
    }

    /**
     * This is a dummy implementation of an authenticator. The authentication
     * information is just being reloaded from session (if exists), otherwise
     * a 'visitor' profile is created for the current user.
     */
    public function authenticate()
    {
        $authResult = [];

        if (Session::has('user_info')) {
            if (session('user_status') == 'visitor') {
                $wrapper = 'default_external_wrapper';
            } else {
                $wrapper = 'default_internal_wrapper';
            }

            $authResult['status'] = session('user_status');
            $authResult['info'] = [
                'email'         =>  session('user_info.email'),
                'timezone'      =>  session('user_info.timezone'),
                'head'          =>  view($wrapper.'.head')->render(),
                'body_top'      =>  view($wrapper.'.body_top')->render(),
                'body_bottom'   =>  view($wrapper.'.body_bottom')->render()
            ];
            $authResult['is_admin'] = session('is_admin');
        } else {
            $authResult['status'] = 'visitor';
            $authResult['info'] = [
                'email'         =>  '',
                'timezone'      =>  '',
                'head'          =>  view('default_external_wrapper.head')->render(),
                'body_top'      =>  view('default_external_wrapper.body_top')->render(),
                'body_bottom'   =>  view('default_external_wrapper.body_bottom')->render()
            ];
            $authResult['is_admin'] = false;
        }

        return $authResult;
    }
}