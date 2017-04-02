<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Response;
use Redirect;
use App\Models\WorkspaceFile;
use Illuminate\Http\Request;
use App\ClassHelpers\UploadValidator;
use App\Http\Controllers\CommonController;

define("PORTAL_LOGIN", "https://portal.lifewatchgreece.eu");

/**
 * Handles the functionality related to importing and exporting files to user's workspace.
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class WorkspaceController extends CommonController
{
    private $workspace_path;
    private $jobs_path;

    public function __construct()
    {
        parent::__construct();
        $this->workspace_path = config('rvlab.workspace_path');
        $this->jobs_path = config('rvlab.jobs_path');

        // Check if cluster storage has been mounted to web server
        if (!$this->check_storage()) {
            if ($this->is_mobile) {
                $response = array('message', 'Storage not found');
                return Response::json($response, 500);
            } else {
                echo $this->load_view('errors/unmounted', 'Storage not found');
                die();
            }
        }
    }

    /**
     * Returns to the user a file located to his workspace
     *
     * @param string $filename
     * @return file|JSON|RedirectResponse
     */
    public function get_file($filename)
    {
        $userInfo = session('user_info');
        $user_workspace_path = $this->workspace_path . '/' . $userInfo['email'];
        $filepath = $user_workspace_path . '/' . basename($filename);

        // Check if such a file belongs to this user
        $count_records = WorkspaceFile::where('user_email', $userInfo['email'])
                ->where('filename', $filename)
                ->count();
        if ($count_records == 0) {
            $this->log_event("User asked for a workspace filename that does not exist in database.", "warning");
            Session::flash('toastr', array('error', 'The filename you provided does not exist in your workspace.'));
            if ($this->is_mobile) {
                $response = array('message', 'The filename you provided does not exist in your workspace.');
                return Response::json($response, 400);
            } else {
                return Redirect::to('/');
            }
        }

        // Check if file exists in file system
        if (!file_exists($filepath)) {
            return $this->unexpectedErrorResponse("User asked for a workspace file that does not exist in filesystem. File path = " . $filepath, "error", "The filename you provided could not be found");
        }

        // Send the file
        return response()->download($filepath);
    }

    /**
     * Copies some example input files to user's workspace
     *
     * @return JSON|RedirectResponse
     */
    public function add_example_data()
    {
        $userInfo = session('user_info');
        $user_workspace_path = $this->workspace_path . '/' . $userInfo['email'];

        try {
            // Create the user workspace if not exists
            if (!file_exists($user_workspace_path)) { // just in case
                mkdir($user_workspace_path);
            }

            // Copy the file to user workspace
            $this->moveExampleToWorkspace('softLagoonEnv.csv');
            $this->moveExampleToWorkspace('softLagoonFactors.csv');
            $this->moveExampleToWorkspace('softLagoonAbundance.csv');
            $this->moveExampleToWorkspace('softLagoonAggregation.csv');
            $this->moveExampleToWorkspace('Macrobenthos_Classes_Adundance.csv');
            $this->moveExampleToWorkspace('Macrobenthos_Crustacea_Adundance.csv');
            $this->moveExampleToWorkspace('Macrobenthos_Femilies_Adundance.csv');

            Session::flash('toastr', array('success', 'Files added to workspace successfully!'));
            if ($this->is_mobile) {
                return Response::json(array(), 200);
            } else {
                return Redirect::to('/');
            }
        } catch (Exception $ex) {
            return $this->unexpectedErrorResponse($ex->getMessage(), 'Something went wrong! Some files may not have been added to your workspace.');
        }
    }

    /**
     * Copies an example file to user's workspace
     *
     * @param string $filename
     */
    protected function moveExampleToWorkspace($filename)
    {
        $userInfo = session('user_info');
        $user_workspace_path = $this->workspace_path . '/' . $userInfo['email'];

        $source = public_path()."/files/$filename";
        $destination = "$user_workspace_path/$filename";

        if (!file_exists($destination)) {
            copy($source, $destination);

            $workspace_file = new WorkspaceFile();
            $workspace_file->user_email = $userInfo['email'];
            $workspace_file->filename = $filename;
            $workspace_file->filesize = filesize($source);
            $workspace_file->save();
        }
    }

    /**
     * Adds some input files to user's workspace
     *
     * @return View|JSON|RedirectResponse
     */
    public function add_files(Request $request)
    {
        $userInfo = session('user_info');
        $user_workspace_path = $this->workspace_path . '/' . $userInfo['email'];

        list($valid_files, $error_messages) = UploadValidator::validate_uploaded_workspace_files($request);

        if (!empty($error_messages)) {
            if ($this->is_mobile) {
                return Response::json($error_messages, 400);
            } else {
                return Redirect::back()->withInput()->withErrors($error_messages);
            }
        }

        if (empty($valid_files)) {
            return Redirect::back();
        }

        $name_conflict = false;

        // Add files to workspace
        if (!file_exists($user_workspace_path)) { // just in case
            mkdir($user_workspace_path);
        }

        DB::beginTransaction();

        $added_files = [];

        try {
            foreach ($valid_files as $file) {
                // Build the destination file path
                $remote_filename = safe_filename($file->getClientOriginalName());
                $destinationFilePath = $user_workspace_path . '/' . $remote_filename;

                if (!file_exists($destinationFilePath)) {
                    $this->moveUploadedToWorkspace($file, $destinationFilePath);

                    $added_files[] = $remote_filename;
                } else {
                    $name_conflict = true;
                }
            }
        } catch (Exception $ex) {
            DB::rollback();

            if (file_exists($destinationFilePath)) {
                unlink($destinationFilePath);
            }

            return $this->unexpectedErrorResponse($ex->getMessage(), 'An error occured! Only the following files were added: '.implode(',', $added_files));
        }

        DB::commit();

        if ($name_conflict) {
            Session::flash('toastr', array('warning', "Some files couldn't be added because a file with the same name already existed!"));
        } else {
            Session::flash('toastr', array('success', 'Files added to workspace successfully!'));
        }

        if ($this->is_mobile) {
            return Response::json(array(), 200);
        } else {
            return Redirect::to('/');
        }
    }

    protected function moveUploadedToWorkspace($file, $destinationFilePath)
    {
        $userInfo = session('user_info');

        $remote_filename = safe_filename($file->getClientOriginalName());
        $sourceFilePath = $file->getPath() . '/' . $file->getBasename();

        // Add a record to database
        $workspace_file = new WorkspaceFile();
        $workspace_file->user_email = $userInfo['email'];
        $workspace_file->filename = $remote_filename;
        $workspace_file->filesize = $file->getSize();
        $workspace_file->save();

        // Copy the file to user workspace and remove the temporary file
        // I don't use $file->move($user_workspace_path,$remote_filename);
        // because there is an issue with moving a file between filesystems
        // causing a "Permission denied" error to be thrown
        copy($sourceFilePath, $destinationFilePath);

        unlink($sourceFilePath);
    }

    /**
     * Copies a job output file from job's folder to user's workspace.
     *
     * @param Request $request
     * @return Response
     */
    public function add_output_file(Request $request)
    {
        $userInfo = session('user_info');
        $user_workspace_path = $this->workspace_path . '/' . $userInfo['email'];

        $form = $request->all();

        // Check if all required information has been posted
        if ((empty($form['filename'])) || (empty($form['jobid']))) {
            $this->log_event("Filename or Job ID is missing.", "illegal");
            $response = array('message', 'Filename or Job ID is missing');
            return Response::json($response, 400);
        }

        // Check if the output file exists
        $job_folder = $this->jobs_path . '/' . $userInfo['email'] . '/job' . $form['jobid'];
        $filepath = $job_folder . '/' . $form['filename'];
        if (!file_exists($filepath)) {
            $this->log_event("File could not be found.", "illegal");
            $response = array('message', 'File could not be found');
            return Response::json($response, 400);
        }

        // Check if the job belongs to this user
        $result = DB::table('jobs')
                ->where('id', $form['jobid'])
                ->where('user_email', $userInfo['email'])
                ->first();

        if (empty($result)) {
            $this->log_event("This job does not belong to this user.", "unathorized");
            $response = array('message', 'This job does not belong to this user');
            return Response::json($response, 401);
        }

        // Create the user workspace if not exists
        if (!file_exists($user_workspace_path)) { // just in case
            mkdir($user_workspace_path);
        }

        // Build the destination file path
        $remote_filename = safe_filename($form['filename']);
        $parts = pathinfo($remote_filename);
        $remote_filename = $parts['filename'] . '_job' . $form['jobid'] . '.' . $parts['extension'];
        $new_filepath = $user_workspace_path . '/' . $remote_filename;
        if (file_exists($new_filepath)) {
            $this->log_event("A file with such a name already exists.", "illegal");
            $response = array('message', 'A file with such a name already exists.');
            return Response::json($response, 428);
        }

        DB::beginTransaction();

        try {

            // Add a record to database
            $workspace_file = new WorkspaceFile();
            $workspace_file->user_email = $userInfo['email'];
            $workspace_file->filename = $remote_filename;
            $workspace_file->filesize = filesize($filepath);
            $workspace_file->save();

            // Copy the file to user workspace
            copy($filepath, $new_filepath);
        } catch (Exception $ex) {
            DB::rollback();
            $this->log_event($ex->getMessage(), "error");
            $response = array('message', 'Unexpected error.');
            return Response::json($response, 500);
        }

        DB::commit();
        return Response::json(array(), 200);
    }

    /**
     * Displays a page for managing the user's input files
     *
     * @return View|JSON
     */
    public function manage(Request $request)
    {
        $userInfo = session('user_info');

        $data = array();

        // List of files that are contained in user's workspace
        $data['workspace_files'] = WorkspaceFile::getUserFiles($userInfo['email']);

        if ($this->is_mobile) {
            return Response::json($data, 200);
        } else {
            return $this->load_view('workspace/manage', 'Home Page', $data);
        }
    }

    /**
     * Deletes an input file from user's workspace
     *
     * @return ResponseRedirect|JSON
     */
    public function remove_file(Request $request)
    {
        $userInfo = session('user_info');

        $form = $request->all();

        if (empty($form['workspace_file'])) {
            $errorMessage = "Workspace file removal was requested without a workspace file id.";
            return $this->illegalActionResponse($errorMessage, 400);
        }

        // Retrieve file information
        list($file_record, $errorMessage, $errorStatus) = $this->workspaceFileExists($form['workspace_file'], $userInfo['email']);

        if (empty($file_record)) {
            return $this->illegalActionResponse($errorMessage, $errorStatus);
        }

        DB::beginTransaction();

        try {
            $file_record->delete();
            $filepath = $this->workspace_path . '/' . $userInfo['email'] . '/' . $file_record->filename;
            unlink($filepath);
        } catch (Exception $ex) {
            DB::rollback();
            return $this->unexpectedErrorResponse($ex->getMessage(), 'Unexpected error!');
        }

        DB::commit();

        Session::flash('toastr', array('success', 'File removed from workspace successfully!'));
        if ($this->is_mobile) {
            return Response::json(array(), 200);
        } else {
            return Redirect::to('/');
        }
    }

    /**
     * Deletes a number of input files from user's workspace
     *
     * @return ResponseRedirect|View|JSON
     */
    public function remove_files(Request $request)
    {
        $userInfo = session('user_info');

        $form = $request->all();

        // Check that list of files is not empty
        if (empty($form['files_to_delete'])) {
            $errorMessage = "Input files removal was requested but no IDs found";
            return $this->illegalActionResponse($errorMessage, 400);
        }

        $files = $form['files_to_delete'];
        foreach ($files as $file) {
            $parts = explode('-', $file);
            $file_id = $parts[2];

            // Retrieve file information
            list($file_record, $errorMessage, $errorStatus) = $this->workspaceFileExists($file_id, $userInfo['email']);

            if (empty($file_record)) {
                $this->log_event($errorMessage, "error");
                if ($this->is_mobile) {
                    $response = array('message', $errorMessage);
                    return Response::json($response, $errorStatus);
                } else {
                    return $this->illegalAction();
                }
            }

            DB::beginTransaction();

            try {
                $file_record->delete();
                $filepath = $this->workspace_path . '/' . $userInfo['email'] . '/' . $file_record->filename;

                unlink($filepath);
            } catch (Exception $ex) {
                DB::rollback();
                return $this->unexpectedErrorResponse($ex->getMessage(), 'Some files could not be deleted!');
            }

            DB::commit();
        }

        Session::flash('toastr', array('success', 'Files removed from workspace successfully!'));
        if ($this->is_mobile) {
            return Response::json(array(), 200);
        } else {
            return Redirect::to('/');
        }
    }

    /**
     * Checks if a workspace file with specific ID exists
     *
     * @param int $file_id
     * @param string $user_email
     * @return array An array in the form of:  [WorkspaceFile $file_record, string $errorMessage, int $errorStatus]
     */
    protected function workspaceFileExists($file_id, $user_email)
    {
        // Check if file ID is a number
        if (!is_numeric($file_id)) {
            return [null, "Workspace file removal was requested with an illegal workspace file id.", 400];
        }

        // Check if file ID is integer
        if ($file_id != intval($file_id)) {
            return [null, "Workspace file removal was requested with an illegal workspace file id.", 400];
        }

        // Retrieve file information
        $file_record = WorkspaceFile::where('id', $file_id)
                ->where('user_email', $user_email)
                ->first();

        // Check that file record is not empty
        if (empty($file_record)) {
            return [null, "Workspace file removal was requested with an illegal workspace file id.", 400];
        }

        // Check that file exists in the filesystem
        $filepath = $this->workspace_path . '/' . $user_email . '/' . $file_record->filename;
        if (!file_exists($filepath)) {
            return [null, "Workspace file could not be found in the file system.", 500];
        }

        return [$file_record, '', null];
    }
}
