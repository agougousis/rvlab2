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
class ResultsPageTest extends TesterBase
{
    protected $mockedAuthenticator;
    protected $loginUrl;
    protected $tempDir = '/home/rvlab2/testing';

    public function setUp(){
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

            $response = $this->call('POST', url('job/serial'), $post_data, [], [], []);
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

        $response = $this->call('POST', url('job/serial'), $post_data, [], [], []);
        $this->assertEquals(302, $response->getStatusCode());

        $job = Job::where('user_email', $this->demoUser)->orderBy('id', 'desc')->first();
        $resultPageResponse = $this->call('get', url('job/'.$job->id));
        $this->assertEquals(200, $resultPageResponse->getStatusCode());

        $content = $resultPageResponse->content();
        $this->assertEquals(1, preg_match("/(.*)Job".$job->id."(.*)/", $content));
        $this->assertEquals(1, preg_match('/(.*)\(taxa2dist\)(.*)/', $content));
        $this->assertEquals(1, preg_match('/(.*)This job has not been executed(.*)/', $content));
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
        $this->assertTrue(in_array($response->getStatusCode(), [200, 428]));

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