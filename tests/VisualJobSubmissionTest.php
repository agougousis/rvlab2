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
class VisualJobSubmissionTest extends CommonTestBase
{
    public function setUp($mockAuthenticator = true)
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
                    'Factor_select2' => 'Location',
                    'Factor_select3' => ''
                ]
            ],
            'cca_visual' => [
                'function' => 'cca_visual',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'softLagoonEnv.csv'
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
                    'Factor_select1' => 'maximumDepthInMeters',
                    'Factor_select2' => 'Temp',
                    'Factor_select3' => 'fieldNumber'
                ]
            ],
            'hclust' => [
                'function' => 'hclust',
                'inputs' => [
                    'box' => 'vegdist_job12.csv'
                ],
                'parameters' => [
                    'method_select' => 'ward.D',
                    'column_select' => 'Station'
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
                    'box2' => 'taxadis_job1.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'column_select' => 'Station',
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
                    'Factor_select1' => 'maximumDepthInMeters',
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
                'function' => 'phylobar',
                'inputs' => [
                    'box' => 'table.nwk',
                    'box2' => 'table.csv'
                ],
                'parameters' => [
                    'top_nodes' => '21'
                ]
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
                'function' => 'mapping_tools_visual',
                'inputs' => [
                    'box' => 'softLagoonAbundance.csv',
                    'box2' => 'softLagoonCoordinatesTransposedLong-Lat.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'top_species' => '21'
                ]
            ],
            'mapping_tools_div_visual' => [
                'function' => 'mapping_tools_div_visual',
                'inputs' => [
                    'box' => 'osd2014-16s-formated.csv',
                    'box2' => '16S-ODV-input-corrected-dec15-formatted-coords.csv',
                    'box3' => '16S-ODV-input-corrected-dec15-formatted.csv'
                ],
                'parameters' => [
                    'transf_method_select' => 'none',
                    'transpose' => 'transpose',
                    'top_species' => '21',
                    'indices_column' => 'Shannon.Index..ln...H.'
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

                if (($data['function'] == 'phylobar')&&(strpos($expectedFilePath, '.jobstatus'))) {
                    continue;
                }

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
                // Trim the value since empty values are sometimes stored as
                // a " " string by the getInputParams() method.
                $given_params[$paramInfo[0]] = trim($paramInfo[1]);
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
