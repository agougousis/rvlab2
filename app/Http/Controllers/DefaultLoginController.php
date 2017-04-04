<?php

namespace App\Http\Controllers;

use Session;
use Response;
use Redirect;
use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;

/**
 * Provides login/logout functionality for the default/dummy authenticator
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class DefaultLoginController extends CommonController
{
    /**
     * Loads the login page
     *
     * @param Request $request
     * @return Response
     */
    public function loginPage(Request $request)
    {
        $userInfo = session('user_info');

        $page = view('template')
                ->with('title', 'R vLab Login Page')
                ->with('head', $userInfo['head'])
                ->with('body_top', $userInfo['body_top'])
                ->with('body_bottom', $userInfo['body_bottom'])
                ->with('content', view('login'));

        return Response::make($page);
    }

    /**
     * Logs a user in
     *
     * @param Request $request
     * @return Response|Redirect
     */
    public function login(Request $request)
    {
        if (($request->input('username') == 'demo@gmail.com')&&($request->input('password') == 'oooooo')) {
            session([
                'user_status' =>  'identified',
                'user_info' =>  [
                    'email'         =>  'demo@gmail.com',
                    'head'          =>  view('default_internal_wrapper.head')->render(),
                    'body_top'      =>  view('default_internal_wrapper.body_top')->render(),
                    'body_bottom'   =>  view('default_internal_wrapper.body_bottom')->render(),
                    'timezone'      =>  'Europe/Athens'
                ],
                'is_admin'  =>  true
            ]);
            Session::save();

            return redirect('/');
        } else {
            return redirect('rlogin')->with('loginMessage', 'aaaa');
        }
    }

    /**
     * Logs a user out
     *
     * @return Redirect
     */
    public function logout()
    {
        session()->flush();
        return Redirect::to('/rlogin');
    }
}
