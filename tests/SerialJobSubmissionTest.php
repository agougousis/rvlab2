<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\WorkspaceFile;
use App\Models\Job;

/**
 * Contains test methods for job submission to serial functions
 *
 * @author Alexandros Gougousis <gougousis@teemail.gr>
 */
class SerialSubmissionTest extends TesterBase
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
     * Tests the job submission functionality for a number of serial functions
     *
     * @test
     * @group submitJob
     */
    public function submit_to_serial_functions()
    {
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
            ],
            'mantel' => [
                'function' => 'mantel',
                'inputs' => [
                    'box' => 'vegdist_output.csv',
                    'box2' => 'vegdist_output.csv'
                ],
                'parameters' => [
                    'permutations' => '999',
                    'method_select' => 'spearman'
                ]
            ],
            'simper' => [
                'function' => 'simper',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'softLagoonFactors.csv'
                ],
                'parameters' => [
                    'transpose' => 'transpose',
                    'column_select' => 'Location',
                    'permutations' => '0',
                    'trace' => 'FALSE'
                ]
            ],
            'permanova' => [
                'function' => 'permanova',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'softLagoonFactors.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'single_or_multi' => 'single',
                    'column_select' => 'Country',
                    'column_select2' => 'Sites',
                    'permutations' => '999',
                    'method_select' => 'euclidean'
                ]
            ],
            'radfit' => [
                'function' => 'radfit',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box' => 'softLagoonFactors.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'column_radfit' => '0'
                ]
            ],
            'bioenv' => [
                'function' => 'bioenv',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'softLagoonEnv.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'method_select' => 'spearman',
                    'index' => 'euclidean',
                    'upto' => '2',
                    'trace' => 'FALSE'
                ]
            ],
            'bict' => [
                'function' => 'bict',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'softLagoonAggregation.csv'
                ],
                'parameters' => [
                    'species_family_select' => 'species'
                ]
            ],
            'convert_to_r' => [
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

            $response = $this->call('POST', url('job/serial'), $post_data, [], [], []);
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
            $this->assertTrue(file_exists($this->demoUserJobsPath . "/$jobf/$jobf.pbs"));
            if ($data['function'] != 'bict') {
                $this->assertTrue(file_exists($this->demoUserJobsPath . "/$jobf/$jobf.R"));
            }
            foreach ($data['inputs'] as $filename) {
                $this->assertTrue(file_exists($this->demoUserJobsPath . "/$jobf/$filename"));
            }

            // Check the database record is correct
            $this->assertEquals($this->demoUser, $job->user_email);
            $this->assertEquals($data['function'], $job->function);
            $this->assertEquals('submitted', $job->status);

            $inputs_string = '';
            foreach ($data['inputs'] as $input) {
                $input = WorkspaceFile::where('user_email', $this->demoUser)->where('filename', $input)->first();
                $inputs_string .= ';' . $input->id . ':' . $input->filename;
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
        }
    }
}
