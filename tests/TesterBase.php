<?php

use App\Models\Job;
use App\Models\SystemLog;
use App\Models\Setting;
use App\Models\JobsLog;
use App\Models\Registration;
use App\Models\WorkspaceFile;
use App\Contracts\Authenticator;

/**
 * Base testing class that contains common functionality
 *
 * @author Alexandros Gougousis <gougousis@teemail.gr>
 */
class TesterBase extends TestCase {

    protected $workspacePath;
    protected $jobsPath;
    protected $demoUser;
    protected $demoUserWorkspacePath;
    protected $demoUserJobsPath;
    protected $mockedAuthenticator;
    protected $loginUrl;
    protected $tempDir = '/home/rvlab2/testing';

    public function setUp()
    {
        parent::setUp();
        $this->loadSchema();

        // Set the paths for the demo user
        $this->demoUser = 'demo@gmail.com';
        $this->workspacePath = config('rvlab.workspace_path');
        $this->jobsPath = config('rvlab.jobs_path');
        $this->demoUserJobsPath = $this->jobsPath . '/' . $this->demoUser;
        $this->demoUserWorkspacePath = $this->workspacePath . '/' . $this->demoUser;

        // Non-mocked authenticator
        $realAuthenticator = app()->make(Authenticator::class);
        $this->loginUrl = $realAuthenticator->getLoginUrl();

        // create a mocked version of the class to be injected
        $this->mockedAuthenticator = $this->createMock(get_class($realAuthenticator));
        $this->mockedAuthenticator
                ->method('getLoginUrl')
                ->willReturn($this->loginUrl);

        // Assigne the mocked object to the Service Container as the same name
        app()->instance(Authenticator::class, $this->mockedAuthenticator);
    }

    /**
     * Builds the database tables (if not exist) and inserts records necessery
     * for the basic functionality.
     */
    protected function loadSchema()
    {
        if (!Schema::hasTable('logs')) {
            Schema::create('logs', function ($table) {
                $table->increments('id');
                $table->string('user_email', 100);
                $table->dateTime('when');
                $table->string('controller', 50);
                $table->string('method', 50);
                $table->string('category', 30);
                $table->string('message', 700);
            });
        }
        SystemLog::query()->truncate();

        if (!Schema::hasTable('settings')) {
            Schema::create('settings', function ($table) {
                $table->increments('id');
                $table->string('sname', 50);
                $table->string('value', 100);
                $table->dateTime('last_modified');
                $table->string('about', 500);
            });

            Setting::insert([
                [
                    'id' => '1',
                    'sname' => 'rvlab_storage_limit',
                    'value' => '1000000000',
                    'last_modified' => '2017-01-26 13:56:06',
                    'about' => 'Total available storage space for R vLab users (in KB)'
                ],
                [
                    'id' => '2',
                    'sname' => 'max_users_supported',
                    'value' => '200',
                    'last_modified' => '2017-01-26 13:56:06',
                    'about' => 'Maximum active users that can be supported by R vLab (in order for each user to have an adequate storage space).'
                ],
                [
                    'id' => '3',
                    'sname' => 'job_max_storagetime',
                    'value' => '30',
                    'last_modified' => '2017-01-26 13:56:06',
                    'about' => "The maximum period for which a user's job is retained (in days). After that period, the job will be automatically be deleted."
                ],
                [
                    'id' => '4',
                    'sname' => 'status_refresh_rate_page',
                    'value' => '30000',
                    'last_modified' => '2017-01-26 13:56:06',
                    'about' => "How often (in milliseconds) the web page makes an AJAX request to update the information about the status of each job"
                ]
            ]);
        }

        if (!Schema::hasTable('registrations')) {
            Schema::create('registrations', function ($table) {
                $table->increments('id');
                $table->string('user_email', 100);
                $table->dateTime('starts');
                $table->dateTime('ends');
            });
        }
        Registration::query()->truncate();

        if (!Schema::hasTable('workspace_files')) {
            Schema::create('workspace_files', function ($table) {
                $table->increments('id');
                $table->string('user_email', 200);
                $table->string('filename', 200);
                $table->bigInteger('filesize');
                $table->timestamp('added_at');
            });
        }

        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function ($table) {
                $table->increments('id');
                $table->string('user_email', 100);
                $table->string('function', 50);
                $table->string('status', 25);
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('submitted_at');
                $table->integer('jobsize')->nullable()->default(0);
                $table->string('inputs', 500)->nullable();
                $table->string('parameters', 500)->nullable();
            });
        }
        Job::query()->truncate();

        if (!Schema::hasTable('jobs_logs')) {
            Schema::create('jobs_logs', function ($table) {
                $table->increments('id');
                $table->string('user_email', 100);
                $table->string('function', 50);
                $table->string('status', 25);
                $table->dateTime('started_at')->nullable();
                $table->dateTime('completed_at')->nullable();
                $table->dateTime('submitted_at');
                $table->integer('jobsize')->default(0);
                $table->string('inputs', 500)->nullable();
                $table->string('parameters', 500)->nullable();
            });
        }
        JobsLog::query()->truncate();
    }

    /**
     * Mocks the authenticator class in order to simulate a user who is logged
     * in and registered to R vLab.
     *
     * @param boolean $admin
     */
    protected function logged_and_registered($admin = false)
    {
        // Log in the demo user by mocking the authenticator class
        $this->mockedAuthenticator
                ->method('authenticate')
                ->willReturn([
                    'status' => 'identified',
                    'info' => [
                        'authorized' => 'yes',
                        'head' => "",
                        'body_top' => "",
                        'body_bottom' => "",
                        'email' => $this->demoUser,
                        'mobile_version' => '',
                        'privileges' => [],
                        'timezone' => 'Europe/Athens',
                    ],
                    'is_admin' => $admin
        ]);

        // Register the demo user
        $yesterday = (new DateTime)->sub(new \DateInterval('P1D'))->format('Y-m-d H:i:s');
        $tomorrow = (new DateTime)->add(new \DateInterval('P1D'))->format('Y-m-d H:i:s');

        Registration::unguard();
        Registration::create([
            'user_email' => $this->demoUser,
            'starts' => $yesterday,
            'ends' => $tomorrow
        ]);
        Registration::reguard();

        $this->createDemoUserDirectories();
    }

    /**
     * Creates the jobs and workspace directories for the demo user
     */
    protected function createDemoUserDirectories()
    {
        // Create the demo user directories
        if (!file_exists($this->demoUserJobsPath)) {
            mkdir($this->demoUserJobsPath);
        }

        if (!file_exists($this->demoUserWorkspacePath)) {
            mkdir($this->demoUserWorkspacePath);
        }
    }

    /**
     * Mocks the authenticator class in order to simulate a user who is logged
     * in but not registered to R vLab.
     *
     * @param boolean $admin
     */
    protected function logged_but_not_registered($admin = false)
    {
        // Log in the demo user by mocking the authenticator class
        $this->mockedAuthenticator
                ->method('authenticate')
                ->willReturn([
                    'status' => 'identified',
                    'info' => [
                        'authorized' => 'yes',
                        'head' => "",
                        'body_top' => "",
                        'body_bottom' => "",
                        'email' => $this->demoUser,
                        'mobile_version' => '',
                        'privileges' => [],
                        'timezone' => 'Europe/Athens',
                    ],
                    'is_admin' => $admin
        ]);

        // Create the demo user directories
        if (!file_exists($this->demoUserJobsPath)) {
            mkdir($this->demoUserJobsPath);
        }

        if (!file_exists($this->demoUserWorkspacePath)) {
            mkdir($this->demoUserWorkspacePath);
        }
    }

    /**
     * Deletes all user workspace directories
     */
    protected function clear_workspace()
    {
        // Delete every workspace directory
        foreach (glob($this->workspacePath . "/*") as $dir) {
            delTree($dir);
        }

        // Remove all workspace files from database
        WorkspaceFile::truncate();
    }

    /**
     * Delete all user job directories
     */
    protected function clear_jobspace()
    {
        // Delete every workspace directory
        foreach (glob($this->jobsPath . "/*") as $dir) {
            delTree($dir);
        }

        // Remove all workspace files from database
        Job::truncate();
    }

    /**
     * Adds demo/testing files to demo user workspace
     */
    protected function add_test_files_to_workspace()
    {
        array_map([$this, 'add_test_file_to_workspace'], glob(__DIR__ . "/files/*"));
    }

    /**
     * Adds a demo/testing file to demo user workspace
     *
     * @param string $filePath
     */
    protected function add_test_file_to_workspace($filePath)
    {
        $filename = basename($filePath);
        copy($filePath, $this->demoUserWorkspacePath . "/$filename");

        WorkspaceFile::unguard();
        WorkspaceFile::create([
            'user_email' => $this->demoUser,
            'filename' => $filename,
            'filesize' => filesize($filePath),
            'added_at' => (new DateTime)->format('Y-m-d H:i:s')
        ]);
        WorkspaceFile::reguard();
    }

    /**
     * Prepares a file in order to be included as attachement to an HTTP
     * request when using the $this->call()
     *
     * @param string $file_to_add
     * @return \Illuminate\Http\UploadedFile
     */
    protected function prepareFileToUpload($file_to_add)
    {
        if (!file_exists($this->tempDir)) {
            mkdir($this->tempDir);
        }

        $filename = basename($file_to_add);
        $newFilePath = $this->tempDir . "/$filename";

        copy($file_to_add, $newFilePath);

        TestCase::assertFileExists($newFilePath);
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $newFilePath);

        return new \Illuminate\Http\UploadedFile(
                $newFilePath, $filename, $mime, null, null, true // for $test
        );
    }

}
