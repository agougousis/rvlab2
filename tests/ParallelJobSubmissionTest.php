<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\WorkspaceFile;
use App\Models\Job;

/**
 * Contains test methods for job submission to parallel functions
 *
 * @author Alexandros Gougousis <gougousis@teemail.gr>
 */
class ParallelJobSubmissionTest extends TesterBase
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
     * Tests the job submission functionality for a number of parallel functions
     *
     * @test
     * @group submitJob
     */
    public function submit_to_parallel_functions()
    {
        $testData = [
            'parallel_taxa2dist' => [
                'function' => 'parallel_taxa2dist',
                'inputs' => [
                    'box' => 'softLagoonAggregation.csv'
                ],
                'parameters' => [
                    'varstep' => 'FALSE',
                    'check_parallel_taxa2dist' => 'TRUE',
                    'No_of_processors' => '2'
                ]
            ],
            'parallel_anosim' => [
                'function' => 'parallel_anosim',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'softLagoonFactors.csv'
                ],
                'parameters' => [
                    'transpose' => 'TRUE',
                    'No_of_processors' => '2',
                    'column_select' => '1',
                    'permutations' => '999',
                    'method_select' => 'euclidean'
                ]
            ],
            'parallel_bioenv' => [
                'function' => 'parallel_bioenv',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'softLagoonEnv.csv'
                ],
                'parameters' => [
                    'transpose' => 'transpose',
                    'No_of_processors' => '2',
                    'method_select' => 'spearman',
                    'index_select' => 'euclidean',
                    'upto' => '2',
                    'trace' => 'FALSE'
                ]
            ],
            'parallel_mantel' => [
                'function' => 'parallel_mantel',
                'inputs' => [
                    'box' => 'vegdist_job12.csv',
                    'box2' => 'vegdist_job12.csv'
                ],
                'parameters' => [
                    'No_of_processors' => '2',
                    'permutations' => '999',
                    'method_select' => 'spearman'
                ]
            ],
            'parallel_permanova' => [
                'function' => 'parallel_permanova',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'softLagoonFactors.csv'
                ],
                'parameters' => [
                    'transpose' => '1',
                    'single_or_multi' => 'single',
                    'column_select' => '1',
                    'column_select2' => '1',
                    'No_of_processors' => '2',
                    'permutations' => '999',
                    'method_select' => 'euclidean'
                ]
            ],
            'parallel_simper' => [
                'function' => 'parallel_simper',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'softLagoonFactors.csv'
                ],
                'parameters' => [
                    'transpose' => 'transpose',
                    'column_select' => '1',
                    'No_of_processors' => '2',
                    'permutations' => '999',
                    'trace' => 'FALSE'
                ]
            ],
            'parallel_postgres_taxa2dist' => [
                'function' => 'parallel_postgres_taxa2dist',
                'inputs' => [
                    'box' => 'softLagoonAggregation.csv'
                ],
                'parameters' => [
                    'varstep' => 'FALSE',
                    'No_of_processors' => '2',
                    'check_parallel_taxa2dist' => 'TRUE'
                ]
            ]
        ];

        foreach ($testData as $data) {
            // Ignore functions with no guidance
            if (empty($data)) {
                continue;
            }
            
            // Submit the job
            $post_data = array_merge($data['parameters'], $data['inputs'], [
                'function' => $data['function'],
                '_token' => csrf_token()
            ]);

            $response = $this->call('POST', url('job'), $post_data, [], [], []);
            $this->assertEquals(302, $response->getStatusCode());

            // Check the toastr message
            if (Session::has('toastr')) {
                $toastr = session('toastr');
                $this->assertEquals('success', $toastr[0]);
            }

            // Retrieve the new job id
            $job = Job::where('user_email', $this->demoUser)->orderBy('id', 'desc')->first();
            $this->assertTrue(!empty($job));

            $jobf = 'job' . $job->id;

            // Check the job folder is there
            $this->assertTrue(file_exists($this->demoUserJobsPath . "/$jobf"));

            // Check the appropriate files are in the job folder
            $actualJobDir = $this->demoUserJobsPath."/$jobf";
            $expectedJobDir = __DIR__."/submitted/".$data['function'];
            foreach(glob($expectedJobDir."/*") as $expectedFilePath) {

                $actualFilePath = $actualJobDir."/".basename($expectedFilePath);

                // The file should exist in the actual job directory
                $this->assertTrue(file_exists($actualFilePath));

                // The file contents should be the same
                $this->assertEquals(file_get_contents($expectedFilePath), file_get_contents($actualFilePath));
            }

            // Check the database record is correct
            $this->assertEquals($this->demoUser, $job->user_email);
            $this->assertEquals($data['function'], $job->function);
            $this->assertEquals('submitted', $job->status);

            $inputs_string = '';
            foreach ($data['inputs'] as $input) {
                $workspaceFile = WorkspaceFile::where('user_email', $this->demoUser)->where('filename', $input)->first();
                $inputs_string .= ';' . $workspaceFile->id . ':' . $workspaceFile->filename;
            }
            $inputs_string = trim($inputs_string, ';');
            $this->assertEquals($inputs_string, $job->inputs);

            // We want to compare the parameters stored in database to the ones
            // that were passed but we don't want to be depended on the order
            //  that parameters were passed or stored in the database string.
            $expected_params = $data['parameters'];

            $given_params = [];
            $paramsPairs = explode(';', $job->parameters);
            foreach ($paramsPairs as $pair) {
                $paramInfo = explode(':', $pair);
                $given_params[$paramInfo[0]] = $paramInfo[1];
            }

            sort($expected_params);
            sort($given_params);

            $this->assertEquals(implode('-', $expected_params), implode('-', $given_params));

            // Clear the database table to keep the Job ID equal to 1
            Job::query()->truncate();

            // Clear the jobs directory
            delTree($actualJobDir);
        }
    }
}
