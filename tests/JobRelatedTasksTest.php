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
class JobRelatedTasksTest extends CommonTestBase
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
     * Try to delete multiple jobs
     *
     * @test
     * @group jobTasks
     */
    public function delete_many_jobs()
    {
        // Add 2 dummy jobs
        $submitted = (new DateTime)->sub(new \DateInterval('P1D'))->format('Y-m-d H:i:s');

        $job1 = new Job();
        $job1->user_email = $this->demoUser;
        $job1->function = 'mapping_tools_div_visual';
        $job1->status = 'completed';
        $job1->submitted_at = $submitted;
        $job1->jobsize = '44';
        $job1->inputs = '148:softLagoonAggregation.csv';
        $job1->parameters = 'varstep:FALSE;check:TRUE';
        $job1->save();

        $job1Dir = $this->demoUserJobsPath.'/job'.$job1->id;
        mkdir($job1Dir);
        foreach (glob(__DIR__.'/mapping_tools_div_visual_completed/*') as $filePath) {
            $filename = basename($filePath);
            copy($filePath, $job1Dir."/$filename");
        }

        // user2 does not exceed his limit. He has 1 job and the files of this
        // jos take 13 KB
        $job2 = new Job();
        $job2->user_email = $this->demoUser;
        $job2->function = 'taxa2dist';
        $job2->status = 'completed';
        $job2->submitted_at = $submitted;
        $job2->jobsize = '44';
        $job2->inputs = '148:softLagoonAggregation.csv';
        $job2->parameters = 'varstep:FALSE;check:TRUE';
        $job2->save();

        $job2Dir = $this->demoUserJobsPath.'/job'.$job2->id;
        mkdir($job2Dir);
        foreach (glob(__DIR__.'/taxa2dist_completed/*') as $filePath) {
            $filename = basename($filePath);
            copy($filePath, $job2Dir."/$filename");
        }

        // Check that the 2 submitted jobs are in place
        $countJobs = Job::where('user_email', $this->demoUser)->count();
        $this->assertEquals(2, $countJobs);

        // Get the job IDs
        $jobIds = Job::where('user_email', $this->demoUser)->select('id')->get()->pluck('id')->toArray();

        // Make a request to delete these 2 jobs
        $post_data = [
            'jobs_for_deletion'  =>  implode(';', $jobIds)
        ];

        $response = $this->call('POST', url('job/delete_many'), $post_data, [], [], []);

        // Check the 2 jobs have been deleted
        $countJobs = Job::where('user_email', $this->demoUser)->count();
        $this->assertEquals(0, $countJobs);
    }
}