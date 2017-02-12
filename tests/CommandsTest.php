<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\WorkspaceFile;
use App\Models\Setting;
use App\Models\Job;

/**
 * Contains test methods for job submission to parallel functions
 *
 * @author Alexandros Gougousis <gougousis@teemail.gr>
 */
class CommandsTest extends TesterBase
{
    public function setUp()
    {
        parent::setUp();

        $this->clear_workspace();
        $this->clear_jobspace();
    }

    /**
     * ...TO DO...
     *
     * @test
     * @group commands
     */
    public function try_to_enforce_storage_policy()
    {
        // Modify the storage limits accordingly, to make testing easier

        // Add at least 2 demo users

        // Add jobs to each user so that the total storage limit has been crossed
        // Ther should be at least one user that does not exceed his personal
        // soft limit and at least one that exceeds that limit.

        // Run the command


        // Check that users who didn't exceed their personal soft limit have
        // their jobs intact.

        // Check that jobs have been deleted from users that exceeded their
        // personal soft limit.
    }

    /**
     * Tests the RefreshStatus command
     *
     * @test
     * @group commands
     */
    public function refresh_submitted_to_completed()
    {
        // Create user directories
        $this->createDemoUserDirectories();

        // Add a database record for a submitted job
        $submitted = (new DateTime)->sub(new \DateInterval('P1D'))->format('Y-m-d H:i:s');

        $job = new Job();
        $job->user_email = $this->demoUser;
        $job->function = 'taxa2dist';
        $job->status = 'submitted';
        $job->submitted_at = $submitted;
        $job->jobsize = '44';
        $job->inputs = '148:softLagoonAggregation.csv';
        $job->parameters = 'varstep:FALSE;check:TRUE';
        $job->save();

        // Add job files that represent a 'completed' status
        $jobDir = $this->demoUserJobsPath.'/job'.$job->id;
        mkdir($jobDir);
        foreach (glob(__DIR__.'/taxa2dist_completed/*') as $filePath) {
            $filename = basename($filePath);
            copy($filePath, $jobDir."/$filename");
        }

        // Run the command
        $command = new App\Console\Commands\RefreshStatus();
        $command->handle();

        // Check the updated status
        $updatedJob = Job::find($job->id);

        $this->assertEquals('completed', $updatedJob->status);
        $this->assertEquals('2017-02-08 15:45:55', $updatedJob->started_at);
        $this->assertEquals('2017-02-08 15:45:57', $updatedJob->completed_at);
    }

    /**
     * Tests the RemoveOldJobs command
     *
     * @test
     * @group commands
     */
    public function remove_old_jobs()
    {
        // Create user directories
        $this->createDemoUserDirectories();

        // Find the date after which we should keep jobs
        $job_max_storagetime = Setting::where('sname','job_max_storagetime')->first(); // should be in days

        $older_acceptable_completion = new DateTime();
        $older_acceptable_completion->sub(new DateInterval('P'.$job_max_storagetime->value.'D'));

        // Add the database record for an old job
        $expired_completion_date = new DateTime();
        $expired_completion_date->sub(new DateInterval('P'.($job_max_storagetime->value + 2).'D'));

        $submission_date = $expired_completion_date->sub(new DateInterval('P1D'));

        $job = new Job();
        $job->user_email = $this->demoUser;
        $job->function = 'taxa2dist';
        $job->status = 'submitted';
        $job->submitted_at = $submission_date;
        $job->started_at = $submission_date;
        $job->completed_at = $expired_completion_date;
        $job->jobsize = '44';
        $job->inputs = '148:softLagoonAggregation.csv';
        $job->parameters = 'varstep:FALSE;check:TRUE';
        $job->save();

        // Add the job files for the old job (we don't really care about the
        // dates mentioned in the files themselves)
        $jobDir = $this->demoUserJobsPath.'/job'.$job->id;
        mkdir($jobDir);
        foreach (glob(__DIR__.'/taxa2dist_completed/*') as $filePath) {
            $filename = basename($filePath);
            copy($filePath, $jobDir."/$filename");
        }

        // Run the command
        $command = new App\Console\Commands\RemoveOldJobs();
        $command->handle();

        // Check that the job has been deleted
        $oldJob = Job::find($job->id);
        $this->assertTrue(empty($oldJob));
        $this->assertFalse(file_exists($jobDir));
    }
}