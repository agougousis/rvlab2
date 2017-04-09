<?php

namespace App\Http\Controllers;

use Response;
use Redirect;
use App\Models\Registration;
use App\Http\Controllers\CommonController;
use App\ClassHelpers\ConditionsChecker;
use Illuminate\Http\Request;

/**
 * Implements functionality that allows a user to register for R vLab.
 * Registration is just a declaration of the user that he is going to use R vLab
 * at least for a period.
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class RegistrationController extends CommonController
{
    /**
     * An helper object that is used to check for necessery conditions
     *
     * @var ConditionsChecker
     */
    private $conditionChecker;

    public function __construct()
    {
        parent::__construct();

        $this->conditionChecker = new ConditionsChecker($this->jobs_path, $this->workspace_path);
        $this->conditionChecker->checkStorage();
    }

    /**
     * Displays the registration page
     *
     * @return View
     */
    public function registrationPage()
    {
        $max_users_suported = $this->system_settings['max_users_supported'];
        $count_current_users = Registration::where('ends', '>', date('Y-m-d H:i:s'))->count();

        if ($count_current_users >= $max_users_suported) {
            return $this->loadView('run_out_of_users', 'Registration Impossible');
        } else {
            return $this->loadView('registration', 'Registration');
        }
    }

    /**
     * Registers a user to R vLab.
     *
     * @return RedirectResponse|JSON
     */
    public function register(Request $response)
    {
        $userInfo = session('user_info');

        $form = $response->all();

        // Make sure the form is valid
        if (empty($form['registration_period'])) {
            if ($this->is_mobile) {
                $response = array(
                    'registered' => 'failed',
                    'message' => 'Registration period was not provided'
                );
                return Response::json($response, 200);
            } else {
                return $this->illegalAction();
            }
        }
        $period = $form['registration_period'];

        // Make sure the registration period is valid
        if (!in_array($period, array('day', 'week', 'month', 'semester'))) {
            if ($this->is_mobile) {
                $response = array(
                    'registered' => 'failed',
                    'message' => 'The registration period is invalid'
                );
                return Response::json($response, 200);
            } else {
                return $this->illegalAction();
            }
        }

        // Decide the registration period dates
        $starts = date('Y-m-d H:i:s');
        $ends = new \DateTime();
        switch ($period) {
            case 'day':
                $ends->add(new \DateInterval('P1D')); // add 60 seconds
                break;
            case 'week':
                $ends->add(new \DateInterval('P7D')); // add 60 seconds
                break;
            case 'month':
                $ends->add(new \DateInterval('P30D')); // add 60 seconds
                break;
            case 'semester':
                $ends->add(new \DateInterval('P6M')); // add 60 seconds
                break;
        }

        // Make the registration
        $registration = new Registration();
        $registration->user_email = $userInfo['email'];
        $registration->starts = $starts;
        $registration->ends = $ends;
        $registration->save();

        if ($this->is_mobile) {
            $response = array('registered' => 'done');
            return Response::json($response, 200);
        } else {
            return Redirect::to('/');
        }
    }
}
