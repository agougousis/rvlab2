<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

/**
 * Contains test methods for functionality related to the list of recent jobs
 *
 * @author Alexandros Gougousis <gougousis@teemail.gr>
 */
class RecentJobsTest extends TesterBase
{
    public function setUp()
    {
        parent::setUp();

        $this->clear_workspace();
        $this->clear_jobspace();
        $this->logged_and_registered();
        $this->add_test_files_to_workspace();
    }

    /**
     * Tests the retrieval of user's job list
     *
     * @test
     * @group recentJobs
     */
    public function get_user_jobs()
    {
        // Sample jobs to submit
        $testData = [
            'taxa2dist' => [
                'function' => 'taxa2dist',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv'
                ],
                'parameters' => [
                    'varstep' => 'FALSE',
                    'check_taxa2dist' => 'TRUE',
                ]
            ],
            'vegdist' => [
                'function' => 'vegdist',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'method_select' => 'euclidean',
                    'binary_select' => 'FALSE',
                    'diag_select' => 'FALSE',
                    'upper_select' => 'FALSE',
                    'na_select' => 'FALSE'
                ]
            ]
        ];

        // Submit the sample jobs
        foreach ($testData as $data) {
            // Submit the job
            $post_data = array_merge($data['parameters'], $data['inputs'], [
                'function' => $data['function'],
                '_token' => csrf_token()
            ]);

            $response = $this->call('POST', url('job/serial'), $post_data, [], [], []);
            $this->assertEquals(302, $response->getStatusCode());
        }

        $response = $this->call('get', url('get_user_jobs'));
        $this->assertEquals(200, $response->getStatusCode());

        // Check there are 2 jobs in the response
        $responseData = json_decode($response->content());
        $this->assertEquals(2, count($responseData));

        // Check information of the vegdist/latest job
        $vegdistJob = $responseData[0];
        $this->assertObjectHasAttribute('user_email', $vegdistJob);
        $this->assertEquals($this->demoUser, $vegdistJob->user_email);
        $this->assertObjectHasAttribute('status', $vegdistJob);
        $this->assertEquals('submitted', $vegdistJob->status);
        $this->assertObjectHasAttribute('function', $vegdistJob);
        $this->assertEquals('vegdist', $vegdistJob->function);
        $this->assertObjectHasAttribute('inputs', $vegdistJob);
        $this->assertEquals(1, preg_match('/(\d+):softLagoonAbundance\.csv/', $vegdistJob->inputs));
        $this->assertObjectHasAttribute('parameters', $vegdistJob);
        $this->assertEquals('transpose:transpose;transofrmation method:none;method:euclidean;binary:FALSE;diag:FALSE;upper:FALSE;na.rm:FALSE', $vegdistJob->parameters);

        // Check information of the taxa2dist job
        $taxa2distJob = $responseData[1];
        $this->assertObjectHasAttribute('user_email', $taxa2distJob);
        $this->assertEquals($this->demoUser, $taxa2distJob->user_email);
        $this->assertObjectHasAttribute('status', $taxa2distJob);
        $this->assertEquals('submitted', $taxa2distJob->status);
        $this->assertObjectHasAttribute('function', $taxa2distJob);
        $this->assertEquals('taxa2dist', $taxa2distJob->function);
        $this->assertObjectHasAttribute('inputs', $taxa2distJob);
        $this->assertEquals(1, preg_match('/(\d+):softLagoonAbundance\.csv/', $taxa2distJob->inputs));
        $this->assertObjectHasAttribute('parameters', $taxa2distJob);
        $this->assertEquals('varstep:FALSE;check_taxa2dist:TRUE', $taxa2distJob->parameters);
    }
}
