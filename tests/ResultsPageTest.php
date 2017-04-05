<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\WorkspaceFile;
use App\Models\Job;

/**
 * Contains test methods for the result page
 *
 * @author Alexandros Gougousis <gougousis@teemail.gr>
 */
class ResultsPageTest extends CommonTestBase
{
    protected $mockedAuthenticator;
    protected $loginUrl;
    protected $tempDir = '/home/rvlab2/testing';

    public function setUp($mockAuthenticator = true){
	parent::setUp();

        $this->clear_workspace();
        $this->clear_jobspace();
        $this->logged_and_registered();
        $this->add_test_files_to_workspace();
    }

    /**
     * Get the status of a specific job
     *
     * @test
     * @group resultPage
     */
    public function get_job_status()
    {
        // Sample jobs to submit
        $testData = [
            'taxa2dist' =>  [
                'function'  =>  'taxa2dist',
                'inputs'    =>  [
                    'box'   =>  'softLagoonAbundance.csv'
                ],
                'parameters'    =>  [
                    'varstep'   =>  'FALSE',
                    'check_taxa2dist'   =>  'TRUE',
                ]
            ],
            'vegdist'   =>  [
                'function'  =>  'vegdist',
                'inputs'    =>  [
                    'box'   =>  'softLagoonAbundance.csv'
                ],
                'parameters'    =>  [
                    'transf_method_select'   =>  'none',
                    'transpose'   =>  'transpose',
                    'method_select' =>  'euclidean',
                    'binary_select' =>  'FALSE',
                    'diag_select'   =>  'FALSE',
                    'upper_select'  =>  'FALSE',
                    'na_select'     =>  'FALSE'
                ]
            ]
        ];

        // Submit the sample jobs
        foreach ($testData as $data) {
            // Submit the job
            $post_data = array_merge($data['parameters'], $data['inputs'], [
                'function'  =>  $data['function'],
                '_token' => csrf_token()
            ]);

            $response = $this->call('POST', url('job'), $post_data, [], [], []);
            $this->assertEquals(302, $response->getStatusCode());
        }

        $response = $this->call('get', url('get_user_jobs'));
        $this->assertEquals(200, $response->getStatusCode());

        // Check there are 2 jobs in the response
        $responseData = json_decode($response->content());
        $this->assertEquals(2, count($responseData));

        $vegdistJob = $responseData[0];
        $taxa2distJob = $responseData[1];

        $response = $this->call('get', url('get_job_status/'.$vegdistJob->id));
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->content());
        $this->assertObjectHasAttribute('status', $responseData);
        $this->assertEquals('submitted', $responseData->status);

        $response = $this->call('get', url('get_job_status/'.$taxa2distJob->id));
        $this->assertEquals(200, $response->getStatusCode());
        $responseData = json_decode($response->content());
        $this->assertObjectHasAttribute('status', $responseData);
        $this->assertEquals('submitted', $responseData->status);
    }

    /**
     * Load the result page of a completed job
     *
     * @test
     * @group resultPage
     */
    public function load_result_page_basic_test()
    {
        $this->clear_workspace();
        $this->clear_jobspace();
        $this->logged_and_registered();
        $this->add_test_files_to_workspace();

        $function_list = [
            'anosim'    =>  26,
            'anova'     =>  30,
            'bioenv'    =>  17,
            'cca'       =>  42,
            'cca_visual'=>  45,
            'hclust'    =>  31,
            'heatcloud' =>  32,
            'mantel'    =>  13,
            'mapping_tools_div_visual'  =>  48,
            'mapping_tools_visual'      =>  49,
            'metamds'   =>  35,
            'metamds_visual'    =>  37,
            'parallel_anosim'   =>  20,
            'parallel_bioenv'   =>  21,
            'parallel_mantel'   =>  22,
            'parallel_permanova'=>  23,
            'parallel_simper'   =>  24,
            'parallel_taxa2dist'=>  19,
            'pca'               =>  29,
            'permanova'         =>  15,
            'phylobar'          =>  60,
            'radfit'            =>  16,
            'regression'        =>  34,
            'second_metamds'    =>  36,
            'simper'            =>  14,
            'taxa2dist'          =>  11,
            'taxondive'         =>  33,
            'vegdist'           =>  12
        ];

        $submitted = (new DateTime)->sub(new \DateInterval('P1D'))->format('Y-m-d H:i:s');
        $started = (new DateTime)->sub(new \DateInterval('PT50M'))->format('Y-m-d H:i:s');
        $completed = (new DateTime)->sub(new \DateInterval('PT30M'))->format('Y-m-d H:i:s');

        foreach ($function_list as $function => $job_id) {
            // Add the relevant database record
            Job::unguard();

            $job = new Job();
            $job->id = $job_id;
            $job->user_email = $this->demoUser;
            $job->function = $function;
            $job->status = 'completed';
            $job->submitted_at = $submitted;
            $job->started_at = $started;
            $job->completed_at = $completed;
            $job->jobsize = '44';
            $job->inputs = '148:softLagoonAggregation.csv';
            $job->parameters = 'varstep:FALSE;check:TRUE';
            $job->save();

            $job->reguard();

            // Add the job directory/files
            $jobDir = $this->demoUserJobsPath.'/job'.$job->id;
            mkdir($jobDir);
            foreach (glob(__DIR__."/completed/$function/*") as $filePath) {
                $filename = basename($filePath);
                copy($filePath, $jobDir."/$filename");
            }

            // Load result page
            $response = $this->call('get', url("job/$job_id"));
            $this->assertEquals(200, $response->getStatusCode());

            $content = $response->content();
            $this->assertEquals(1, preg_match("/(.*)Job".$job_id."(.*)/", $content));
            $this->assertEquals(1, preg_match("/(.*)\($function\)(.*)/", $content));
        }
    }

    /**
     * Checks the result page of a (just) submitted job
     *
     * @test
     * @group resultPage
     */
    public function submitted_job()
    {
        $this->clear_workspace();
        $this->clear_jobspace();
        $this->logged_and_registered();
        $this->add_test_files_to_workspace();

        $jobConfig = [
            'function'  =>  'taxa2dist',
            'inputs'    =>  [
                'box'   =>  'softLagoonAbundance.csv'
            ],
            'parameters'    =>  [
                'varstep'   =>  'FALSE',
                'check_taxa2dist'   =>  'TRUE',
            ]
        ];

        // Submit the job
        $post_data = array_merge($jobConfig['parameters'], $jobConfig['inputs'], [
            'function'  =>  $jobConfig['function'],
            '_token' => csrf_token()
        ]);

        $response = $this->call('POST', url('job'), $post_data, [], [], []);
        $this->assertEquals(302, $response->getStatusCode());

        // Load result page
        $job = Job::where('user_email', $this->demoUser)->orderBy('id', 'desc')->first();
        $resultPageResponse = $this->call('get', url('job/'.$job->id));
        $this->assertEquals(200, $resultPageResponse->getStatusCode());

        $content = $resultPageResponse->content();
        $this->assertEquals(1, preg_match("/(.*)Job".$job->id."(.*)/", $content));
        $this->assertEquals(1, preg_match('/(.*)\(taxa2dist\)(.*)/', $content));
        $this->assertEquals(1, preg_match('/(.*)This job has not been executed(.*)/', $content));
    }

    /**
     * Try to load a job page for a job Id that does not exist
     *
     * @test
     * @group resultPage
     */
    public function ask_job_that_does_not_exist()
    {
        $this->clear_workspace();
        $this->clear_jobspace();
        $this->logged_and_registered();

        // Make a call to job page
        $resultPageResponse = $this->call('get', url('job/85'));
        $this->assertEquals(302, $resultPageResponse->getStatusCode());
    }

    /**
     * Try to load a job page for a job Id that does not exist from mobile
     *
     * @test
     * @group resultPage
     */
    public function ask_job_that_does_not_exist_from_mobile()
    {
        $this->clear_workspace();
        $this->clear_jobspace();
        $this->logged_and_registered();

        // Make the same call from mobile
        $response = $this->call('get', url('job/85'), [], [], [], ['HTTP_AAAA1'=>'aaa']);
        $this->assertEquals(400, $response->getStatusCode());
    }

    /**
     * Checks the result page of a completed job (general case)
     *
     * @test
     * @group resultPage
     */
    public function completed_taxa2dist_job()
    {
        $this->clear_workspace();
        $this->clear_jobspace();
        $this->logged_and_registered();
        $this->add_test_files_to_workspace();

        // Add the relevant database record
        $submitted = (new DateTime)->sub(new \DateInterval('P1D'))->format('Y-m-d H:i:s');
        $started = (new DateTime)->sub(new \DateInterval('PT50M'))->format('Y-m-d H:i:s');
        $completed = (new DateTime)->sub(new \DateInterval('PT30M'))->format('Y-m-d H:i:s');

        $job = new Job();
        $job->user_email = $this->demoUser;
        $job->function = 'taxa2dist';
        $job->status = 'completed';
        $job->submitted_at = $submitted;
        $job->started_at = $started;
        $job->completed_at = $completed;
        $job->jobsize = '44';
        $job->inputs = '148:softLagoonAggregation.csv';
        $job->parameters = 'varstep:FALSE;check:TRUE';
        $job->save();

        // Add the job directory/files
        $jobDir = $this->demoUserJobsPath.'/job'.$job->id;
        mkdir($jobDir);
        foreach (glob(__DIR__.'/taxa2dist_completed/*') as $filePath) {
            $filename = basename($filePath);
            copy($filePath, $jobDir."/$filename");
        }

        // Load the page and check the contents
        $resultPageResponse = $this->call('get', url('job/'.$job->id));
        $this->assertEquals(200, $resultPageResponse->getStatusCode());

        $content = $resultPageResponse->content();
        $this->assertEquals(1, preg_match("/(.*)Input parameters(.*)/", $content));
        $this->assertEquals(1, preg_match("/(.*)Files produced as output(.*)/", $content));
        $this->assertEquals(1, preg_match("/(.*)proc.time\(\)(.*)/", $content));
        $this->assertEquals(1, preg_match("/(.*)taxadis.csv(.*)/", $content));
    }

    /**
     * Tests the functionality of moving a job output file to user's workspace
     *
     * @test
     * @group resultPage
     */
    public function add_taxa2dist_output_to_workspace()
    {
        $this->clear_workspace();
        $this->clear_jobspace();
        $this->logged_and_registered();
        $this->add_test_files_to_workspace();

        // Add the relevant database record
        $submitted = (new DateTime)->sub(new \DateInterval('P1D'))->format('Y-m-d H:i:s');
        $started = (new DateTime)->sub(new \DateInterval('PT50M'))->format('Y-m-d H:i:s');
        $completed = (new DateTime)->sub(new \DateInterval('PT30M'))->format('Y-m-d H:i:s');

        $job = new Job();
        $job->user_email = $this->demoUser;
        $job->function = 'taxa2dist';
        $job->status = 'completed';
        $job->submitted_at = $submitted;
        $job->started_at = $started;
        $job->completed_at = $completed;
        $job->jobsize = '44';
        $job->inputs = '148:softLagoonAggregation.csv';
        $job->parameters = 'varstep:FALSE;check:TRUE';
        $job->save();

        // Add the job directory/files
        $jobDir = $this->demoUserJobsPath.'/job'.$job->id;
        mkdir($jobDir);
        foreach (glob(__DIR__.'/taxa2dist_completed/*') as $filePath) {
            $filename = basename($filePath);
            copy($filePath, $jobDir."/$filename");
        }

        $post_data = [
            'filename'  =>  'taxadis.csv',
            'jobid' =>  '1'
        ];
        $response = $this->call('post', url('workspace/add_output_file'), $post_data);
        $this->assertTrue(in_array($response->getStatusCode(), [200, 302]));

        // Check file is in workspace
        $this->assertTrue(file_exists($this->demoUserWorkspacePath.'/taxadis.csv'));

        // Check relevant record is in database
        $record = WorkspaceFile::where('user_email', $this->demoUser)->where('filename', 'taxadis.csv')->first();
        $this->assertTrue(!empty($record));
    }

    /**
     * Tests the functionality of downloading a job output file
     *
     * @test
     * @group resultPage
     */
    public function get_job_file()
    {
        $this->clear_workspace();
        $this->clear_jobspace();
        $this->logged_and_registered();
        $this->add_test_files_to_workspace();

        // Add the relevant database record
        $submitted = (new DateTime)->sub(new \DateInterval('P1D'))->format('Y-m-d H:i:s');
        $started = (new DateTime)->sub(new \DateInterval('PT50M'))->format('Y-m-d H:i:s');
        $completed = (new DateTime)->sub(new \DateInterval('PT30M'))->format('Y-m-d H:i:s');

        $job = new Job();
        $job->user_email = $this->demoUser;
        $job->function = 'taxa2dist';
        $job->status = 'completed';
        $job->submitted_at = $submitted;
        $job->started_at = $started;
        $job->completed_at = $completed;
        $job->jobsize = '44';
        $job->inputs = '148:softLagoonAggregation.csv';
        $job->parameters = 'varstep:FALSE;check:TRUE';
        $job->save();

        // Add the job directory/files
        $jobDir = $this->demoUserJobsPath.'/job'.$job->id;
        mkdir($jobDir);
        foreach (glob(__DIR__.'/taxa2dist_completed/*') as $filePath) {
            $filename = basename($filePath);
            copy($filePath, $jobDir."/$filename");
        }

        $response = $this->call('get', url('storage/get_job_file/job/1/taxadis.csv'));
        $this->assertEquals(200, $response->getStatusCode());
    }
}