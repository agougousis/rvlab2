<?php

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Models\WorkspaceFile;

/**
 * Contains test methods for the workspace functionality
 *
 * @author Alexandros Gougousis <gougousis@teemail.gr>
 */
class WorkspaceManagementTest extends TesterBase
{
    public function setUp()
    {
        parent::setUp();
    }

    /**
     * Tests the retrieval of user storage utilization info
     *
     * @test
     * @group workspace
     */
    public function get_user_storage_utilization()
    {
        $this->logged_and_registered();

        // Retrieve the itilization information
        $response = $this->call('GET', '/workspace/user_storage_utilization');
        $this->assertEquals(200, $response->getStatusCode());
        $utilization = json_decode($response->getContent());

        // Check the JSON response structure
        $this->assertObjectHasAttribute('storage_utilization', $utilization);
        $this->assertObjectHasAttribute('totalsize', $utilization);
    }

    /**
     * Tests the status change functionality for the workspace (UI) tab
     *
     * @test
     * @group workspace
     */
    public function change_tab_status()
    {
        $this->logged_and_registered();

        $response = $this->call('POST', url('workspace/tab_status'), ['new_status' => 'closed', '_token' => csrf_token()], [], [], []);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('closed', session('workspace_tab_status'));

        $response = $this->call('POST', url('workspace/tab_status'), ['new_status' => 'open', '_token' => csrf_token()], [], [], []);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('open', session('workspace_tab_status'));
    }

    /**
     * Tests the functionality of adding example/sample data to user's workspace
     *
     * @test
     * @group workspace
     */
    public function add_example_data()
    {
        $this->clear_workspace();
        $this->logged_and_registered();

        $response = $this->call('POST', 'workspace/add_example_data', ['_token' => csrf_token()], [], [], []);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertRedirectedTo('/');

        $countWorkspaceFiles = WorkspaceFile::where('user_email', $this->demoUser)->count();
        $this->assertEquals(7, $countWorkspaceFiles);
        $this->assertEquals(7, count(glob($this->demoUserWorkspacePath . '/*')));
    }

    /**
     * Tests the functionality of removing a file from user's workspace
     *
     * @test
     * @group workspace
     */
    public function remove_file_from_workspace()
    {
        $this->clear_workspace();
        $this->logged_and_registered();
        $this->add_test_files_to_workspace();

        $fileToRemove = WorkspaceFile::where('filename', 'table.csv')->where('user_email', $this->demoUser)->first();

        $response = $this->call('POST', 'workspace/remove_file', ['_token' => csrf_token(), 'workspace_file' => $fileToRemove->id], [], [], []);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertRedirectedTo('/');

        $countWorkspaceFiles = WorkspaceFile::where('user_email', $this->demoUser)->count();
        $this->assertEquals(17, $countWorkspaceFiles);
        $this->assertEquals(17, count(glob($this->demoUserWorkspacePath . '/*')));
    }

    /**
     * Tests the functionality of removing more than one files
     * from user's workspace
     *
     * @test
     * @group workspace
     */
    public function remove_files_from_workspace()
    {
        $this->clear_workspace();
        $this->logged_and_registered();
        $this->add_test_files_to_workspace();

        $file1ToRemove = WorkspaceFile::where('filename', 'table.csv')->where('user_email', $this->demoUser)->first();
        $file2ToRemove = WorkspaceFile::where('filename', 'table2.csv')->where('user_email', $this->demoUser)->first();

        $postData = [
            '_token' => csrf_token(),
            'files_to_delete' => [
                'i-checkbox-' . $file1ToRemove->id,
                'i-checkbox-' . $file2ToRemove->id
            ]
        ];
        $response = $this->call('POST', 'workspace/remove_files', $postData, [], [], []);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertRedirectedTo('/');

        $countWorkspaceFiles = WorkspaceFile::where('user_email', $this->demoUser)->count();
        $this->assertEquals(16, $countWorkspaceFiles);
        $this->assertEquals(16, count(glob($this->demoUserWorkspacePath . '/*')));
    }

    /**
     * Tests the functionality of downloading a file from user's workspace
     *
     * @test
     * @group workspace
     */
    public function get_workspace_file()
    {
        $this->clear_workspace();
        $this->logged_and_registered();
        $this->add_test_files_to_workspace();

        $response = $this->call('get', 'workspace/get/table.csv');
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/plain', $response->getFile()->getMimeType());
        $this->assertEquals('table.csv', $response->getFile()->getFilename());
    }

    /**
     * Tests the functionality of adding files to user's workspace
     *
     * @test
     * @group workspace
     */
    public function add_files_to_workspace()
    {
        $this->clear_workspace();
        $this->logged_and_registered();

        $file_to_add = __DIR__ . '/files/softLagoonAbundance.csv';
        $uploadFile = $this->prepareFileToUpload($file_to_add);

        $this->get('/');
        $response = $this->call('POST', url('workspace/add_files'), ['_token' => csrf_token()], [], ['local_files' => [$uploadFile]], []);
        $this->assertEquals(302, $response->getStatusCode());

        $fileRecord = WorkspaceFile::where('filename', 'softLagoonAbundance.csv')->where('user_email', $this->demoUser)->first();
        $this->assertTrue(!empty($fileRecord));
        $this->assertEquals(filesize($file_to_add), $fileRecord->filesize);

        $expectedFileDestination = $this->demoUserWorkspacePath . '/softLagoonAbundance.csv';
        $this->assertTrue(file_exists($expectedFileDestination));
    }

    /**
     * Tests the functionality of loading the worksoace management view
     *
     * @test
     * @group workspace
     */
    public function get_manage_page()
    {
        $this->logged_and_registered();

        $response = $this->call('GET', '/workspace/manage');
        $this->assertEquals(200, $response->getStatusCode());
    }
}
