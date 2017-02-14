<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\Registration;

/**
 * Contains test methods for the Authentication/Authorization middleware
 *
 * @author Alexandros Gougousis <gougousis@teemail.gr>
 */
class AuthorizationMiddlewareTest extends TesterBase
{
    public function setUp() {
        parent::setUp();
    }

    /**
     * Tests that users who are not logged in are redirected to the login page
     *
     * @test
     * @group aai
     */
    public function should_redirect_visitors_to_login_page()
    {
        $this->mockedAuthenticator
                ->method('authenticate')
                ->willReturn([
                    'status' => 'visitor',
                    'info' => null,
                    'is_admin' => false
        ]);

        // Test default/web response
        $response = $this->call('GET', '/');
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertRedirectedTo($this->loginUrl);

        // Test mobile response
        $response = $this->call('GET', '/', [], [], [], ['HTTP_AAAA1'=>'aaa']);
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * Tests that logged in users who are not registered to R vLab are
     * redirected to registration page
     *
     * @test
     * @group aai
     */
    public function should_redirect_unregistered_users_to_rvlab_registration_page()
    {
        $user_email = 'demo@gmail.com';

        $this->mockedAuthenticator
                ->method('authenticate')
                ->willReturn([
                    'status' => 'identified',
                    'info' => [
                        'authorized' => 'yes',
                        'head' => "",
                        'body_top' => "",
                        'body_bottom' => "",
                        'email' => $user_email,
                        'mobile_version' => '',
                        'privileges' => [],
                        'timezone' => '',
                    ],
                    'is_admin' => false
        ]);

        // Test default/web response
        $response = $this->call('GET', '/');
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertRedirectedTo('registration');

        // Test mobile response
        $response = $this->call('GET', '/', [], [], [], ['HTTP_AAAA1'=>'aaa']);
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * Checks that users who are not registered are not redirected when trying
     * to visit the registration page
     *
     * @test
     * @group aai
     */
    public function should_not_redirect_unregistered_users_visiting_the_registration_page()
    {
        $user_email = 'demo@gmail.com';

        $this->mockedAuthenticator
                ->method('authenticate')
                ->willReturn([
                    'status' => 'identified',
                    'info' => [
                        'authorized' => 'yes',
                        'head' => "",
                        'body_top' => "",
                        'body_bottom' => "",
                        'email' => $user_email,
                        'mobile_version' => '',
                        'privileges' => [],
                        'timezone' => '',
                    ],
                    'is_admin' => false
        ]);

        $response = $this->call('GET', '/registration');
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Checks that the registration page cannot be accessed by registered users
     *
     * @test
     * @group aai
     */
    public function registered_users_cannot_visit_the_registration_page()
    {
        $user_email = 'demo@gmail.com';

        $this->mockedAuthenticator
                ->method('authenticate')
                ->willReturn([
                    'status' => 'identified',
                    'info' => [
                        'authorized' => 'yes',
                        'head' => "",
                        'body_top' => "",
                        'body_bottom' => "",
                        'email' => $user_email,
                        'mobile_version' => '',
                        'privileges' => [],
                        'timezone' => '',
                    ],
                    'is_admin' => false
        ]);

        $yesterday = (new DateTime)->sub(new \DateInterval('P1D'))->format('Y-m-d H:i:s');
        $tomorrow = (new DateTime)->add(new \DateInterval('P1D'))->format('Y-m-d H:i:s');

        Registration::unguard();
        Registration::create([
            'user_email' => $user_email,
            'starts' => $yesterday,
            'ends' => $tomorrow
        ]);
        Registration::reguard();

        // Test default/web response
        $response = $this->call('GET', 'registration');
        $this->assertEquals(302, $response->getStatusCode());

        // Test mobile response
        $response = $this->call('GET', 'registration', [], [], [], ['HTTP_AAAA1'=>'aaa']);
        $this->assertEquals(403, $response->getStatusCode());
    }

    /**
     * Checks that admin toolbar is not displayed to non-admin users
     *
     * @test
     * @group aai
     */
    public function not_admin_user_should_not_see_admin_toolbar()
    {
        $user_email = 'demo@gmail.com';

        $this->mockedAuthenticator
                ->method('authenticate')
                ->willReturn([
                    'status' => 'identified',
                    'info' => [
                        'authorized' => 'yes',
                        'head' => "",
                        'body_top' => "",
                        'body_bottom' => "",
                        'email' => $user_email,
                        'mobile_version' => '',
                        'privileges' => [],
                        'timezone' => '',
                    ],
                    'is_admin' => false
        ]);

        $yesterday = (new DateTime)->sub(new \DateInterval('P1D'))->format('Y-m-d H:i:s');
        $tomorrow = (new DateTime)->add(new \DateInterval('P1D'))->format('Y-m-d H:i:s');

        Registration::unguard();
        Registration::create([
            'user_email' => $user_email,
            'starts' => $yesterday,
            'ends' => $tomorrow
        ]);
        Registration::reguard();

        $response = $this->call('GET', '/');
        $this->assertEquals(200, $response->getStatusCode());
        $this->visit('/')->dontSee('Admin Toolbar');
    }

    /**
     * Checks that admin toolbar is visible to admin users
     *
     * @test
     * @group aai
     */
    public function admin_user_should_see_admin_toolbar()
    {
        $user_email = 'demo@gmail.com';

        $this->mockedAuthenticator
                ->method('authenticate')
                ->willReturn([
                    'status' => 'identified',
                    'info' => [
                        'authorized' => 'yes',
                        'head' => "",
                        'body_top' => "",
                        'body_bottom' => "",
                        'email' => $user_email,
                        'mobile_version' => '',
                        'privileges' => [],
                        'timezone' => '',
                    ],
                    'is_admin' => true
        ]);

        $yesterday = (new DateTime)->sub(new \DateInterval('P1D'))->format('Y-m-d H:i:s');
        $tomorrow = (new DateTime)->add(new \DateInterval('P1D'))->format('Y-m-d H:i:s');

        Registration::unguard();
        Registration::create([
            'user_email' => $user_email,
            'starts' => $yesterday,
            'ends' => $tomorrow
        ]);
        Registration::reguard();

        $response = $this->call('GET', '/');
        $this->assertEquals(200, $response->getStatusCode());
        $this->visit('/')->see('Admin Toolbar');
    }
}
