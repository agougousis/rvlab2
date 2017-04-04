<?php

namespace App\Contracts;

/**
 * An interface for authenticator classes.
 *
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
interface Authenticator
{
    /**
     * Returns information about the user's identity
     *
     * This method provides information about the user that extends beyond the
     * identity. The information is expected to be an array with the following
     * structure:
     *
     * $info = [
     *      'status'    =>  'visitor'|'identified',
     *      'info'      =>  [
     *          'email'     =>  '',
     *          'timezone'  =>  '',
     *          'head'      =>  '',
     *          'body_top'  =>  '',
     *          'body_bottom'   =>  ''
     *      ],
     *      'is_admin'  =>  true|false
     * ];
     *
     * The user's email is used as the user's ID. This field is required and
     * should be empty in case the user has not logged in (is a 'visitor')
     *
     * The 'head', 'body_top' and 'body_bottom' values are expected to be html
     * code that will be included in the relevant positions of the template and
     * are optional. Nonetheless, the current UI has been based to Bootstrap
     * CSS Framework and jQuery, so the relevant tags that include these
     * libraries are expected to be part of the 'head' value.
     */
    public function authenticate();

    /**
     * Returns the URL where visitors should be redirected to in order to login.
     */
    public function getLoginUrl();
}
