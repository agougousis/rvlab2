<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\WorkspaceFile;
use App\Models\Setting;
use App\Models\Job;
use App\Models\SystemLog;

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
     * Tests the \App\Console\CommandsStoragePolicy class
     *
     * @test
     * @group commands
     */
    public function try_to_enforce_storage_policy()
    {
        // Modify the storage limits accordingly, to make testing easier
        $rvlab_storage_limit = Setting::where('sname', 'rvlab_storage_limit')->first();
        $rvlab_storage_limit->value = 10000; // 10.000 KB = 10 MB
        $rvlab_storage_limit->save();

        $max_users_supported = Setting::where('sname', 'max_users_supported')->first();
        $max_users_supported->value = 4; // => 2.5 MB/user
        $max_users_supported->save();

        /**
         * The utilization limit for enforcing storage policy has been set to 20%.
         * (check the StoragePolicy class to be sure)
         **/

        // Add at least 2 demo users
        $user1 = 'user1@gmail.com';
        $user1UserJobsPath = $this->jobsPath . '/' . $user1;
        $user1UserWorkspacePath = $this->workspacePath . '/' . $user1;
        if (!file_exists($user1UserJobsPath)) {
            mkdir($user1UserJobsPath);
        }
        if (!file_exists($user1UserWorkspacePath)) {
            mkdir($user1UserWorkspacePath);
        }

        $user2 = 'user2@gmail.com';
        $user2UserJobsPath = $this->jobsPath . '/' . $user2;
        $user2UserWorkspacePath = $this->workspacePath . '/' . $user2;

        if (!file_exists($user2UserJobsPath)) {
            mkdir($user2UserJobsPath);
        }
        if (!file_exists($user2UserWorkspacePath)) {
            mkdir($user2UserWorkspacePath);
        }

        // Add jobs to each user so that the total storage limit has been crossed
        // Ther should be at least one user that does not exceed his personal
        // soft limit and at least one that exceeds that limit.

        // user1 exceeds his limit. He has 1 job and the files of this jos
        // take 2.7 MB
        $submitted = (new DateTime)->sub(new \DateInterval('P1D'))->format('Y-m-d H:i:s');

        $job1 = new Job();
        $job1->user_email = $user1;
        $job1->function = 'mapping_tools_div_visual';
        $job1->status = 'completed';
        $job1->submitted_at = $submitted;
        $job1->jobsize = '44';
        $job1->inputs = '148:softLagoonAggregation.csv';
        $job1->parameters = 'varstep:FALSE;check:TRUE';
        $job1->save();

        $job1Dir = $user1UserJobsPath.'/job'.$job1->id;
        mkdir($job1Dir);
        foreach (glob(__DIR__.'/mapping_tools_div_visual_completed/*') as $filePath) {
            $filename = basename($filePath);
            copy($filePath, $job1Dir."/$filename");
        }

        // user2 does not exceed his limit. He has 1 job and the files of this
        // jos take 13 KB
        $job2 = new Job();
        $job2->user_email = $user2;
        $job2->function = 'taxa2dist';
        $job2->status = 'completed';
        $job2->submitted_at = $submitted;
        $job2->jobsize = '44';
        $job2->inputs = '148:softLagoonAggregation.csv';
        $job2->parameters = 'varstep:FALSE;check:TRUE';
        $job2->save();

        $job2Dir = $user2UserJobsPath.'/job'.$job2->id;
        mkdir($job2Dir);
        foreach (glob(__DIR__.'/taxa2dist_completed/*') as $filePath) {
            $filename = basename($filePath);
            copy($filePath, $job2Dir."/$filename");
        }

        // Run the command
        $command = new App\Console\Commands\StoragePolicy();
        $command->handle();

        // Check that users who didn't exceed their personal soft limit have
        // their jobs intact.
        $countUser2Jobs = Job::where('user_email', $user2)->get()->count();
        $this->assertEquals(1, $countUser2Jobs);
        $this->assertTrue(file_exists($job2Dir));

        // Check that jobs have been deleted from users that exceeded their
        // personal soft limit.
        $countUser1Jobs = Job::where('user_email', $user1)->get()->count();
        $this->assertEquals(0, $countUser1Jobs);
        $this->assertFalse(file_exists($job1Dir));
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
     * Tests the RefreshStatus command by failing
     *
     * @test
     * @group commands
     */
    public function fail_to_refresh_submitted_to_completed()
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

        /** Ommit the creation of job directory/files **/

        // Run the command
        $command = new App\Console\Commands\RefreshStatus();
        $command->handle();

        // Check the updated status
        $updatedJob = Job::find($job->id);
        $this->assertEquals('submitted', $updatedJob->status);

        // Check that an error has been logged
        $countErrorLogs = SystemLog::all()->count();
        $this->assertEquals(1, $countErrorLogs);

        $error = SystemLog::first();
        $this->assertEquals('RefreshStatusCommand', $error->method);
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

    /**
     * Tests the RemoveOldJobs command by failing
     *
     * @test
     * @group commands
     */
    public function fail_to_delete_job_folder_while_removing_old_jobs()
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

        // Rename the job directory
        $renamedJobDir = $this->demoUserJobsPath.'/newJob'.$job->id;
        shell_exec("mv $jobDir $renamedJobDir");

        // Run the command
        $command = new App\Console\Commands\RemoveOldJobs();
        $command->handle();

        // Check that the job has not been deleted
        $oldJob = Job::find($job->id);
        $this->assertFalse(empty($oldJob));
        $this->assertTrue(file_exists($renamedJobDir));

        // Check that an error has been logged
        $countErrorLogs = SystemLog::all()->count();
        $this->assertEquals(1, $countErrorLogs);

        $error = SystemLog::first();
        $this->assertEquals('RemoveOldJobsCommand', $error->method);
    }
}