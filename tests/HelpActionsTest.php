<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Contains test methods for the Help functionality
 *
 * @author Alexandros Gougousis <gougousis@teemail.gr>
 */
class HelpActionsTest extends TesterBase
{
    public function setUp()
    {
        parent::setUp();
    }

    /**
     * Checks that the "R vLab Storage Policy" page is displayed correctly
     *
     * @test
     * @group help
     */
    public function display_storage_policy()
    {
        $this->logged_and_registered();

        $response = $this->call('get', 'help/storage_policy');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, preg_match('/(.*)R vLab Storage Policy(.*)/', $response->getContent()));
    }

    /**
     * Checks that the "About R vLab" page is displayed correctly
     *
     * @test
     * @group help
     */
    public function about_rvlab()
    {
        $this->clear_workspace();
        $this->logged_and_registered();
        $this->add_test_files_to_workspace();

        $this->assertTrue(file_exists(dirname(__DIR__) . '/public/files/R_vlab_about.pdf'));
//        $response = $this->call('get', asset('files/R_vlab_about.pdf'));
//        $this->assertEquals(200, $response->getStatusCode());
//        $this->assertEquals('text/plain', $response->getFile()->getMimeType());
//        $this->assertEquals('table.csv', $response->getFile()->getFilename());
    }

    /**
     * Checks that the R vLab help video page is displayed correctly
     *
     * @test
     * @group help
     */
    public function help_video()
    {
        $this->clear_workspace();
        $this->logged_and_registered();
        $this->add_test_files_to_workspace();

        $response = $this->call('get', 'help/video');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, preg_match('/(.*)<iframe(.*)/', $response->getContent()));
    }
}
