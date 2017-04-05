<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\Setting;

/**
 * Contains test methods for the administration functionality
 *
 * @author Alexandros Gougousis <gougousis@teemail.gr>
 */
class AdminTest extends CommonTestBase
{
    public function setUp($mockAuthenticator = true) {
        parent::setUp();
    }

    /**
     * Checks that admin pages are not available to non-admins
     *
     * @test
     * @group admin
     */
    public function admin_pages_not_available_to_non_admins()
    {
        $this->logged_and_registered(false);

        $response = $this->call('get', 'admin');
        $this->assertEquals(302, $response->getStatusCode());

        $response = $this->call('get', 'admin/last_errors');
        $this->assertEquals(302, $response->getStatusCode());

        $response = $this->call('get', 'admin/job_list');
        $this->assertEquals(302, $response->getStatusCode());

        $response = $this->call('get', 'admin/storage_utilization');
        $this->assertEquals(302, $response->getStatusCode());

        $response = $this->call('get', 'admin/statistics');
        $this->assertEquals(302, $response->getStatusCode());

        $response = $this->call('get', 'admin/configure');
        $this->assertEquals(302, $response->getStatusCode());
    }

    /**
     * Tests that administration main page loads successfuly
     *
     * @test
     * @group admin
     */
    public function load_admin_main_page()
    {
        $this->logged_and_registered(true);
        $response = $this->call('get', 'admin');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, preg_match('/(.*)Admin Pages:(.*)/', $response->getContent()));
    }

    /**
     * Tests that the "Recent errors" administration page loads successfuly
     *
     * @test
     * @group admin
     */
    public function load_recent_errors_page()
    {
        $this->logged_and_registered(true);
        $response = $this->call('get', 'admin/last_errors');
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Tests that the "Recent Jobs" administration page loads successfuly
     *
     * @test
     * @group admin
     */
    public function load_recent_jobs_page()
    {
        $this->logged_and_registered(true);
        $response = $this->call('get', 'admin/job_list');
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Tests that the "Storage Utilization" administration page loads successfuly
     *
     * @test
     * @group admin
     */
    public function load_storage_utilization_page()
    {
        $this->clear_workspace();
        $this->logged_and_registered(true);
        $this->add_test_files_to_workspace();

        $response = $this->call('get', 'admin/storage_utilization');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, preg_match('/(.*)Per User Storage Utilization(.*)/', $response->getContent()));
    }

    /**
     * Tests that the "Statistics" administration page loads successfuly
     *
     * @test
     * @group admin
     */
    public function load_statistics_page()
    {
        $this->logged_and_registered(true);
        $response = $this->call('get', 'admin/statistics');
        $this->assertEquals(200, $response->getStatusCode());
    }

    /**
     * Tests that the "System Configuration" administration page loads successfuly
     *
     * @test
     * @group admin
     */
    public function load_configuration_page()
    {
        $this->logged_and_registered(true);
        $response = $this->call('get', 'admin/configure');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(1, preg_match('/(.*)max_users_supported(.*)/', $response->getContent()));
    }

    /**
     * Tests the system configuration functionality
     *
     * @test
     * @group admin
     */
    public function change_configuration()
    {
        $this->logged_and_registered(true);

        $modified_configs = [
            'max_users_supported' => 201
        ];
        $response = $this->call('post', 'admin/configure', $modified_configs);
        $this->assertEquals(302, $response->getStatusCode());

        $max_users_setting = Setting::where('sname', 'max_users_supported')->first();
        $this->assertEquals(201, $max_users_setting->value);
    }
}
