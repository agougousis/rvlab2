<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\Job;
use App\Models\SystemLog;

/**
 * Contains test methods for functionality related to custom exceptions
 *
 * @author Alexandros Gougousis <gougousis@teemail.gr>
 */
class CustomExceptionstTest extends CommonTestBase
{
    public function setUp($mockAuthenticator = true)
    {
        parent::setUp();
    }

    /**
     * Test the App\Exceptions\InvalidRequestException
     *
     * @test
     * @group exceptions
     */
    public function trigger_invalid_request_exception()
    {
        /** We will try to add to workspace an output file that does not exist **/

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

        // Request an output file that does not exist
        $post_data = [
            'filename'  =>  'taxadis5.csv',
            'jobid' =>  '1'
        ];

        $response = $this->call('post', url('workspace/add_output_file'), $post_data);
        $this->assertEquals(302, $response->getStatusCode());

        // Check the log from InvalidRequestException is there
        $count_invalid_logs = SystemLog::where('category', 'invalid')->get()->count();
        $this->assertEquals(1, $count_invalid_logs);
    }

    /**
     * Test the App\Exceptions\UnexpectedRequestException
     *
     * @test
     * @group exceptions
     */
    public function trigger_unexpected_request_exception()
    {
        $this->logged_and_registered();

        /** We will try to add to workspace an output file that does not exist **/

        $this->clear_workspace();
        $this->clear_jobspace();
        $this->logged_and_registered();
        $this->add_test_files_to_workspace();

        // Delete the workspace file to be requested
        unlink($this->demoUserWorkspacePath . '/table.csv');

        // Request a workspace file that does not exist
        $response = $this->call('get', 'workspace/get/table.csv');
        $this->assertEquals(302, $response->getStatusCode());

        // Check the log from InvalidRequestException is there
        $count_invalid_logs = SystemLog::where('category', 'illegal')->get()->count();
        $this->assertEquals(1, $count_invalid_logs);
    }

    /**
     * Test the App\Exceptions\AuthorizationException
     *
     * @test
     * @group exceptions
     */
    public function trigger_authorization_exception()
    {
        $this->clear_workspace();
        $this->clear_jobspace();
        $this->logged_and_registered();
        $this->add_test_files_to_workspace();

        // Paths for a dummy user
        $dummyUser = 'dummy@gmail.com';
        $this->workspacePath = config('rvlab.workspace_path');
        $this->jobsPath = config('rvlab.jobs_path');
        $this->dummyUserJobsPath = $this->jobsPath . '/' . $dummyUser;
        $this->dummyUserWorkspacePath = $this->workspacePath . '/' . $dummyUser;

        // Add the relevant database record
        $submitted = (new DateTime)->sub(new \DateInterval('P1D'))->format('Y-m-d H:i:s');
        $started = (new DateTime)->sub(new \DateInterval('PT50M'))->format('Y-m-d H:i:s');
        $completed = (new DateTime)->sub(new \DateInterval('PT30M'))->format('Y-m-d H:i:s');

        $job = new Job();
        $job->user_email = $dummyUser;
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

        // Request an output file from a job that does not belong to demo user
        $response = $this->call('get', url("storage/get_job_file/job/$job->id/job1.R"));
        $this->assertEquals(401, $response->getStatusCode());

        // Check the log from InvalidRequestException is there
        $count_invalid_logs = SystemLog::where('category', 'unauthorized')->get()->count();
        $this->assertEquals(1, $count_invalid_logs);
    }
}
