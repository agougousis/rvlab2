<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Contains test methods for job submission to parallel functions
 *
 * @author Alexandros Gougousis <gougousis@teemail.gr>
 */
class LocalAuthenticatorTest extends CommonTestBase
{
    public function setUp($mockAuthenticator = true)
    {
        parent::setUp(false);

        $this->clear_workspace();
        $this->clear_jobspace();
    }

    /**
     * Tests the DefaultAuthenticator
     *
     * @test
     * @group auth
     */
    public function load_login_page_and_login()
    {
        // We need this in order not to be redirected to registration page
        $this->register_demo_user();

        $this->visit('/')
                ->seePageIs('rlogin')
                ->seeElement("input[id='username']")
                ->type('demo@gmail.com', 'username')
                ->type('oooooo', 'password')
                ->press('Sign in')
                ->seePageIs('/');
    }
}