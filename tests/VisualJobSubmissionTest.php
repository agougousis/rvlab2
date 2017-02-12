<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\Job;
use App\Models\WorkspaceFile;

/**
 * Contains test methods for job submission to visual functions
 *
 * @author Alexandros Gougousis <gougousis@teemail.gr>
 */
class VisualJobSubmissionTest extends TesterBase
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
     * Tests the job submission functionality for a number of visual functions
     *
     * @test
     * @group submitJob
     */
    public function submit_to_visual_functions()
    {
        $testData = [
            'anosim' => [
                'function' => 'anosim',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'softLagoonFactors.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'method_select' => 'euclidean',
                    'column_select' => 'Country',
                    'permutations' => '999'
                ]
            ],
            'cca' => [
                'function' => 'cca',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'softLagoonFactors.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'Factor_select1' => 'Country',
                    'Factor_select2' => 'Sites',
                    'Factor_select3' => ''
                ]
            ],
            'cca_visual' => [
                'function' => 'cca_visual',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'softLagoonFactors.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'Factor_select1' => 'maximumDepthInMeters',
                    'Factor_select2' => 'Temp',
                    'Factor_select3' => '',
                    'top_species' => '21'
                ]
            ],
            'pca' => [
                'function' => 'pca',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'softLagoonFactors.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'column_select' => 'Country'
                ]
            ],
            'anova' => [
                'function' => 'anova',
                'inputs' => [
                    'box' => 'softLagoonEnv.csv'
                ],
                'parameters' => [
                    'one_or_two_way' => 'one',
                    'Factor_select1' => 'maximumDepthInMeter',
                    'Factor_select2' => 'Temp',
                    'Factor_select3' => 'fieldNumber'
                ]
            ],
            'hclust' => [
                'function' => 'hclust',
                'inputs' => [
                    'box' => 'vegdist_output.csv'
                ],
                'parameters' => [
                    'method_select' => 'ward.D',
                    'column_select' => 'Class'
                ]
            ],
            'heatcloud' => [
                'function' => 'heatcloud',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'top_species' => '21'
                ]
            ],
            'taxondive' => [
                'function' => 'taxondive',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'taxa2dist_output.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'column_select' => 'Class',
                    'match_force' => 'FALSE',
                    'deltalamda' => 'Delta'
                ]
            ],
            'regression' => [
                'function' => 'regression',
                'inputs' => [
                    'box' => 'softLagoonEnv.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'single_or_multi' => 'single',
                    'Factor_select1' => 'maximumDepthInMeter',
                    'Factor_select2' => 'Temp',
                    'Factor_select3' => 'fieldNumber'
                ]
            ],
            'metamds' => [
                'function' => 'metamds',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'softLagoonFactors.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'method_select' => 'euclidean',
                    'column_select' => 'Country',
                    'k_select' => '12',
                    'trymax' => '20',
                    'autotransform_select' => 'TRUE',
                    'noshare' => '0.1',
                    'wascores_select' => 'TRUE',
                    'expand' => 'TRUE',
                    'trace' => '1'
                ]
            ],
            'phylobar' => [
            ],
            'second_metamds' => [
                'function' => 'second_metamds',
                'inputs' => [
                    'box' => [
                        'Macrobenthos-Classes-Adundance.csv',
                        'Macrobenthos-Crustacea-Adundance.csv',
                        'Macrobenthos-Femilies-Adundance.csv'
                    ]
                ],
                'parameters' => [
                    'transpose' => '',
                    'transf_method_select' => 'none',
                    'method_select' => 'euclidean',
                    'cor_method_select' => 'spearman',
                    'k_select' => '2',
                    'trymax' => '20',
                    'autotransform_select' => 'TRUE',
                    'noshare' => '0.1',
                    'wascores_select' => 'TRUE',
                    'expand' => 'TRUE',
                    'trace' => '1'
                ]
            ],
            'metamds_visual' => [
                'function' => 'metamds_visual',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'top_species' => '21',
                    'method_select_viz' => 'euclidean',
                    'k_select_viz' => '12',
                    'trymax_viz' => '20'
                ]
            ],
            'mapping_tools_visual' => [
            ],
            'mapping_tools_div_visual' => [
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

            $response = $this->call('POST', url('job/visual'), $post_data, [], [], []);
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
            $this->assertTrue(file_exists($this->demoUserJobsPath . "/$jobf/$jobf.R"));
            foreach ($data['inputs'] as $input) {
                if (is_array($input)) {
                    foreach ($input as $filename) {
                        $this->assertTrue(file_exists($this->demoUserJobsPath . "/$jobf/$filename"));
                    }
                } else {
                    $this->assertTrue(file_exists($this->demoUserJobsPath . "/$jobf/$input"));
                }
            }

            // Check the database record is correct
            $this->assertEquals($this->demoUser, $job->user_email);
            $this->assertEquals($data['function'], $job->function);
            $this->assertEquals('submitted', $job->status);

            $inputs_string = '';
            foreach ($data['inputs'] as $input) {
                // In 2nd stage metamds an input can be array of filenames, so...
                if (is_array($input)) {
                    foreach ($input as $filename) {
                        $input = WorkspaceFile::where('user_email', $this->demoUser)->where('filename', $filename)->first();
                        $inputs_string .= ';' . $input->id . ':' . $input->filename;
                    }
                } else {
                    $input = WorkspaceFile::where('user_email', $this->demoUser)->where('filename', $input)->first();
                    $inputs_string .= ';' . $input->id . ':' . $input->filename;
                }
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
