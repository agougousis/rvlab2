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
     * Calculate storage utilization for the logged in user
     *
     * @return Response
     */
    public function user_storage_utilization()
    {
        $userInfo = session('user_info');
        $max_users_supported = $this->system_settings['max_users_supported'];
        $rvlab_storage_limit = $this->system_settings['rvlab_storage_limit'];
        $jobs_path = config('rvlab.jobs_path');
        $workspace_path = config('rvlab.workspace_path');

        $inputspace_totals = directory_size($workspace_path . '/' . $userInfo['email']); // in KB
        $jobspace_totals = directory_size($jobs_path . '/' . $userInfo['email']); // in KB

        $response = array(
            'storage_utilization' => 100 * ($inputspace_totals + $jobspace_totals) / ($rvlab_storage_limit / $max_users_supported),
            'totalsize' => $inputspace_totals + $jobspace_totals
        );

        return Response::json($response, 200);
    }

    /**
     * Saves the new state of "Workspace File Management" tab
     *
     * @return Response
     */
    public function change_tab_status(Request $request)
    {
        if ($request->has('new_status')) {
            $new_status = $request->input('new_status');
            if ($new_status == 'open')
                Session::put('workspace_tab_status', 'open');
            else
                Session::put('workspace_tab_status', 'closed');
        }

        return Response::json(array(), 200);
    }

    /**
     * Cleans a CSV column name.
     *
     * It removes from column name the new line character and quotes, replaces
     * any character that is not alphanumeric (or underscore) with dot, trims any
     * leading or trailing space and if the remaining string is comprised only by
     * digits it adds an 'X' at the front.
     * an 'X' character
     *
     * @param string $header_value
     * @return string
     */
    private function clean_header($header_value)
    {
        $header_value = trim(preg_replace('/\r\n|\r|\n/', '', $header_value));
        $header_value = trim(preg_replace('/\"/', '', $header_value));
        $header_value = trim(preg_replace('/[^A-Za-z0-9\_]/', '.', $header_value));
        // If first character is number, put an "X" in front of everything
        if (is_numeric(substr($header_value, 0, 1))) {
            $header_value = "X" . $header_value;
        }

        return $header_value;
    }

    /**
     * Retrieves the column names from a CSV file
     *
     * @param string $filename
     * @return JSON
     */
    public function convert2r_tool($filename)
    {
        $userInfo = session('user_info');
        $user_workspace_path = $this->workspace_path . '/' . $userInfo['email'];
        $filepath = $user_workspace_path . '/' . $filename;

        if (file_exists($filepath)) {
            $lines_file = file($filepath);

            $header_values = explode(",", $lines_file[0]);
            $headers = array();

            foreach ($header_values as $value) {
                $headers[] = $this->clean_header($value);
            }

            $response = array(
                'headers' => $headers,
            );

            return Response::json($response, 200);
        } else {
            $this->log_event("File could not be found.", "error");
            $response = array('message', 'File could not be found.');
            return Response::json($response, 500);
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
            $this->log_event("User asked for a workspace file that does not exist in filesystem. File path = " . $filepath, "error");
            Session::flash('toastr', array('error', 'The filename you provided could not be found.'));
            if ($this->is_mobile) {
                $response = array('message', 'The filename you provided could not be found.');
                return Response::json($response, 500);
            } else {
                return Redirect::to('/');
            }
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
            $this->importExampleFile('softLagoonEnv.csv');
            $this->importExampleFile('softLagoonFactors.csv');
            $this->importExampleFile('softLagoonAbundance.csv');
            $this->importExampleFile('softLagoonAggregation.csv');
            $this->importExampleFile('Macrobenthos_Classes_Adundance.csv');
            $this->importExampleFile('Macrobenthos_Crustacea_Adundance.csv');
            $this->importExampleFile('Macrobenthos_Femilies_Adundance.csv');

            Session::flash('toastr', array('success', 'Files added to workspace successfully!'));
            if ($this->is_mobile) {
                return Response::json(array(), 200);
            } else {
                return Redirect::to('/');
            }
        } catch (Exception $ex) {
            $this->log_event($ex->getMessage(), "error");
            Session::flash('toastr', array('error', 'Something went wrong! Some files may not have been added to your workspace.'));
            if ($this->is_mobile) {
                $response = array('message', 'Something went wrong! Some files may not have been added to your workspace.');
                return Response::json($response, 500);
            } else {
                return Redirect::to('/');
            }
        }
    }

    /**
     * Copies an example file to user's workspace
     *
     * @param string $filename
     */
    protected function importExampleFile($filename)
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
                $new_filepath = $user_workspace_path . '/' . $remote_filename;

                $sourceFilePath = $file->getPath() . '/' . $file->getBasename();
                $destinationFilePath = $user_workspace_path . '/' . $remote_filename;

                if (!file_exists($new_filepath)) {
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

                    $added_files[] = $remote_filename;

                    unlink($sourceFilePath);
                } else {
                    $name_conflict = true;
                }
            }
        } catch (Exception $ex) {
            DB::rollback();

            if (file_exists($destinationFilePath)) {
                unlink($destinationFilePath);
            }

            $this->log_event($ex->getMessage(), "error");

            if ($this->is_mobile) {
                $response = array('message', 'An error occured! Only the following files were added: '.implode(',', $added_files));
                return Response::json($response, 500);
            } else {
                return $this->unexpected_error();
            }
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
            $this->log_event("Workspace file removal was requested without a workspace file id.", "error");
            if ($this->is_mobile) {
                $response = array('message', 'Workspace file removal was requested without a workspace file id.');
                return Response::json($response, 400);
            } else {
                return $this->illegalAction();
            }
        }

        $file_record = WorkspaceFile::where('id', $form['workspace_file'])
                ->where('user_email', $userInfo['email'])
                ->first();

        if (empty($file_record)) {
            $this->log_event("Workspace file removal was requested with an illegal workspace file id.", "error");
            if ($this->is_mobile) {
                $response = array('message', 'Workspace file removal was requested with an illegal workspace file id.');
                return Response::json($response, 400);
            } else {
                return $this->illegalAction();
            }
        }

        $filepath = $this->workspace_path . '/' . $userInfo['email'] . '/' . $file_record->filename;
        if (!file_exists($filepath)) {
            $this->log_event("Workspace file could not be found in the file system.", "error");
            if ($this->is_mobile) {
                $response = array('message', 'Workspace file could not be found in the file system.');
                return Response::json($response, 500);
            } else {
                return $this->illegalAction();
            }
        }

        DB::beginTransaction();

        try {
            $file_record->delete();
            unlink($filepath);
        } catch (Exception $ex) {
            DB::rollback();
            $this->log_event($ex->getMessage(), "error");
            if ($this->is_mobile) {
                $response = array('message', 'Unexpected error.');
                return Response::json($response, 500);
            } else {
                return $this->unexpected_error();
            }
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
            $this->log_event("Input files removal was requested but no IDs found.", "error");
            if ($this->is_mobile) {
                $response = array('message', 'Input files removal was requested but no IDs found.');
                return Response::json($response, 400);
            } else {
                return $this->illegalAction();
            }
        }

        $files = $form['files_to_delete'];
        foreach ($files as $file) {
            $parts = explode('-', $file);
            $file_id = $parts[2];

            // Check if file ID is a number
            if (!is_numeric($file_id)) {
                return $this->illegalAction();
            }

            // Check if file ID is integer
            if ($file_id != intval($file_id)) {
                return $this->illegalAction();
            }

            // Retrieve file information
            $file_record = WorkspaceFile::where('id', $file_id)
                    ->where('user_email', $userInfo['email'])
                    ->first();

            // Check that file record is not empty
            if (empty($file_record)) {
                $this->log_event("Workspace file removal was requested with an illegal workspace file id.", "error");
                if ($this->is_mobile) {
                    $response = array('message', 'Workspace file removal was requested with an illegal workspace file id.');
                    return Response::json($response, 400);
                } else {
                    return $this->illegalAction();
                }
            }

            // Check that file exists in the filesystem
            $filepath = $this->workspace_path . '/' . $userInfo['email'] . '/' . $file_record->filename;
            if (!file_exists($filepath)) {
                $this->log_event("Workspace file could not be found in the file system.", "error");
                if ($this->is_mobile) {
                    $response = array('message', 'Workspace file could not be found in the file system.');
                    return Response::json($response, 500);
                } else {
                    return $this->illegalAction();
                }
            }

            DB::beginTransaction();

            try {
                $file_record->delete();
                unlink($filepath);
            } catch (Exception $ex) {
                DB::rollback();
                $this->log_event($ex->getMessage(), "error");
                if ($this->is_mobile) {
                    $response = array('message', 'Some files could not be deleted!');
                    return Response::json($response, 500);
                } else {
                    return $this->unexpected_error();
                }
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
}
