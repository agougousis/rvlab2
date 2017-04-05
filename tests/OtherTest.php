<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Contains test methods for various functionality
 *
 * @author Alexandros Gougousis <gougousis@teemail.gr>
 */
class OtherTest extends CommonTestBase
{
    public function setUp($mockAuthenticator = true)
    {
        parent::setUp();
    }

    /**
     * Tests the CSRF token functionality
     *
     * @test
     * @group other
     */
    public function get_csrf_token()
    {
        $this->logged_and_registered();

        $response = $this->call('GET', 'get_token');
        $this->assertEquals(200, $response->getStatusCode());
        $responseObject = json_decode($response->content());
        $this->assertObjectHasAttribute('token', $responseObject);
        $this->assertObjectHasAttribute('when', $responseObject);
    }

    /**
     * Tests the form retrieval functionality
     *
     * @test
     * @group other
     */
    public function get_function_form()
    {
        $this->logged_and_registered();

        $response = $this->call('GET', 'mobile/forms/taxa2dist');
        $this->assertEquals(200, $response->getStatusCode());
        $responseObject = json_decode($response->content());
        $this->assertObjectHasAttribute('structure', $responseObject);
        $this->assertObjectHasAttribute('tooltips', $responseObject);

        $structure = $responseObject->structure;
        $this->assertEquals('/job/serial', $structure->url);
        $this->assertObjectHasAttribute('inputs', $structure);
        $this->assertObjectHasAttribute('parameters', $structure);
    }

}
