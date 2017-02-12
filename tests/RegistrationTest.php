<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\Registration;

/**
 * Contains test methods for the R vLab registration functionality
 *
 * @author Alexandros Gougousis <gougousis@teemail.gr>
 */
class RegistrationTest extends TesterBase
{
    public function setUp()
    {
        parent::setUp();
    }

    /**
     * Checks that the registration page loads correctly
     *
     * @test
     * @group registration
     */
    public function display_registration_page()
    {
        $this->logged_but_not_registered();

        $response = $this->call('get', 'registration');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, preg_match('/(.*)Select and submit(.*)/', $response->getContent()));
    }

    /**
     * Tests the registration functionality
     *
     * @test
     * @group registration
     */
    public function register()
    {
        $this->logged_but_not_registered();

        $now = (new Datetime)->format('Y-m-d H:i:s');

        $response = $this->call('post', 'registration', ['registration_period' => 'week']);
        $this->assertEquals(302, $response->getStatusCode());

        $registration = Registration::where('user_email', $this->demoUser)->where('starts', '<=', $now)->where('ends', '>', $now)->first();
        $this->assertTrue(!empty($registration));
    }

    /**
     * 
     * @group registration
     */
    public function mobile_user_checks_if_registered()
    {
        $this->logged_but_not_registered();

        $response = $this->call('get', 'is_registered/demo@gmail.com', ['X-HTTP_AAAA1' => 'aaa']);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
