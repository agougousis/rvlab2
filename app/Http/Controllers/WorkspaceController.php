<?php

namespace App\Http\Controllers;

use DB;
use Session;
use Response;
use Validator;
use App\Models\WorkspaceFile;
use Illuminate\Http\Request;
use App\ClassHelpers\UploadValidator;
use App\ClassHelpers\WorkspaceHelper;
use App\ClassHelpers\AuthorizationChecker;
use App\ClassHelpers\ConditionsChecker;
use App\Http\Controllers\CommonController;
use Symfony\Component\HttpFoundation\File\UploadedFile;

define("PORTAL_LOGIN", "https://portal.lifewatchgreece.eu");

/**
 * Handles the functionality related to importing and exporting files to user's workspace.
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class WorkspaceController extends CommonController
{
    /**
     * The directory path to R vLab workspace
     *
     * @var string
     */
    private $workspace_path;

    /**
     * The directory path to R vLab jobs
     *
     * @var string
     */
    private $jobs_path;

    /**
     * An helper object that is used to check for necessery conditions
     *
     * @var ConditionsChecker
     */
    private $conditionChecker;

    public function __construct()
    {
        parent::__construct();
        $this->workspace_path = config('rvlab.workspace_path');
        $this->jobs_path = config('rvlab.jobs_path');

        $this->conditionChecker = new ConditionsChecker($this->jobs_path, $this->workspace_path);

        // Check if cluster storage has been mounted to web server
        if (!$this->checkStorage()) {
            if ($this->is_mobile) {
                $response = array('message', 'Storage not found');
                return Response::json($response, 500);
            } else {
                echo $this->loadView('errors/unmounted', 'Storage not found');
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
    public function getFile($filename)
    {
        // INPUT FILTERING: Any workspace file stored in R vLab has gone
        // through the safe_filename() cleaning procedure
        $safe_filename = safe_filename(basename($filename));

        // CONDITION: Check if such a file exists for this user
        $filepath = $this->conditionChecker->workspaceFileBelongsToUser($safe_filename, session('user_info.email'));

        // Send the file
        return response()->download($filepath);
    }

    /**
     * Copies some example input files to user's workspace
     *
     * @return JSON|RedirectResponse
     */
    public function addExampleData()
    {
        $user_email = session('user_info.email');
        $user_workspace_path = $this->workspace_path . '/' . $user_email;

        try {
            // Create the user workspace if not exists
            if (!file_exists($user_workspace_path)) { // just in case
                mkdir($user_workspace_path);
            }

            // Copy the file to user workspace
            $workspaceHelper = new WorkspaceHelper($user_email, $this->workspace_path);
            $workspaceHelper->moveExampleToWorkspace('softLagoonEnv.csv');
            $workspaceHelper->moveExampleToWorkspace('softLagoonFactors.csv');
            $workspaceHelper->moveExampleToWorkspace('softLagoonAbundance.csv');
            $workspaceHelper->moveExampleToWorkspace('softLagoonAggregation.csv');
            $workspaceHelper->moveExampleToWorkspace('Macrobenthos_Classes_Adundance.csv');
            $workspaceHelper->moveExampleToWorkspace('Macrobenthos_Crustacea_Adundance.csv');
            $workspaceHelper->moveExampleToWorkspace('Macrobenthos_Femilies_Adundance.csv');

            return $this->okResponse('Files added to workspace successfully!');
        } catch (\Exception $ex) {
            $exception = new UnexpectedErrorException($ex->getMessage());
            $exception->setUserMessage('Something went wrong! Some files may not have been added to your workspace.');
            throw $exception;
        }
    }

    /**
     * Adds some input files to user's workspace
     *
     * @return View|JSON|RedirectResponse
     */
    public function addFiles(Request $request)
    {
        $user_workspace_path = $this->workspace_path . '/' . session('user_info.email');

        // INPUT FILTERING: Validate files has been uploaded correctly and are
        // valid (in terms of type, size etc.)
        $valid_files = UploadValidator::validateUploadedWorkspaceFiles($request);

        $name_conflict = false;

        // Build user workspace directory, if not exists
        if (!file_exists($user_workspace_path)) { // just in case
            mkdir($user_workspace_path);
        }

        // Add files to workspace
        DB::beginTransaction();

        $added_files = [];

        try {
            foreach ($valid_files as $file) {
                $this->addFileToWorkspace($file, $user_workspace_path, $added_files, $name_conflict);
            }
        } catch (\Exception $ex) {
            DB::rollback();

            foreach ($added_files as $file) {
                $remote_filename = safe_filename($file->getClientOriginalName());
                $destinationFilePath = $user_workspace_path . '/' . $remote_filename;
                unlink($destinationFilePath);
            }

            return $this->unexpectedErrorResponse($ex->getMessage(), 'An error occured! Only the following files were added: '.implode(',', $added_files));
        }

        DB::commit();

        if ($name_conflict) {
            Session::flash('toastr', array('warning', "Some files couldn't be added because a file with the same name already existed!"));
        } else {
            Session::flash('toastr', array('success', count($valid_files).' files added to workspace successfully!'));
        }

        return $this->okResponse();
    }

    /**
     * Adds a single uploaded file to user's workspace
     *
     * @param UploadedFile $file
     * @param string $user_workspace_path
     * @param array $added_files
     * @param boolean $name_conflict
     */
    protected function addFileToWorkspace(UploadedFile $file, $user_workspace_path, &$added_files, &$name_conflict)
    {
        // Build the destination file path
        $remote_filename = safe_filename($file->getClientOriginalName());
        $destinationFilePath = $user_workspace_path . '/' . $remote_filename;

        if (!file_exists($destinationFilePath)) {
            $workspaceHelper = new WorkspaceHelper(session('user_info.email'), $this->workspace_path);
            $workspaceHelper->moveUploadedToWorkspace($file, $destinationFilePath);

            $added_files[] = $remote_filename;
        } else {
            $name_conflict = true;
        }
    }

    /**
     * Copies a job output file from job's folder to user's workspace.
     *
     * @param Request $request
     * @return Response
     */
    public function addOutputFile(Request $request)
    {
        $form = $request->all();

        // INPUT FILTERING: Check form data
        $validator = Validator::make($form, [
                    'filename'  => 'required|string|max:200',
                    'jobid'     => 'required|int'
        ]);

        if ($validator->fails()) {
            $this->logEvent("Filename or Job ID is missing.", "illegal");
            $response = array('message', 'Filename or Job ID is missing');
            return Response::json($response, 400);
        }

        // INPUT FILTERING: Remove path from filename
        $output_filename = basename($form['filename']);

        $user_email = session('user_info.email');
        $user_workspace_path = $this->workspace_path . '/' . $user_email;

        // CONDITION: Check that the job output file exists
        $filepath = $this->conditionChecker->outputFilepathExists($user_email, $form['jobid'], $output_filename);

        $parts = pathinfo($output_filename);
        $remote_filename = $parts['filename'] . '_job' . $form['jobid'] . '.' . $parts['extension'];
        $new_filepath = $user_workspace_path . '/' . $remote_filename;

        // CONDITION: Check that there is no file in workspace with the same
        // name.
        $this->conditionChecker->workspaceFilenameIsNotTaken($new_filepath);

        // ACCESS CONTROL: Check if the job belongs to this user
        AuthorizationChecker::jobBelongsToUser($form['jobid'], $user_email);

        // Create the user workspace if not exists
        if (!file_exists($user_workspace_path)) { // just in case
            mkdir($user_workspace_path);
        }

        DB::beginTransaction();

        try {
            // Add a record to database
            $workspace_file = new WorkspaceFile();
            $workspace_file->user_email = $user_email;
            $workspace_file->filename = $remote_filename;
            $workspace_file->filesize = filesize($filepath);
            $workspace_file->save();

            // Copy the file to user workspace
            copy($filepath, $new_filepath);
        } catch (\Exception $ex) {
            DB::rollback();
            $this->logEvent($ex->getMessage(), "error");
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
        $data = array();

        // List of files that are contained in user's workspace
        $data['workspace_files'] = WorkspaceFile::getUserFiles(session('user_info.email'));

        if ($this->is_mobile) {
            return Response::json($data, 200);
        } else {
            return $this->loadView('workspace/manage', 'Home Page', $data);
        }
    }

    /**
     * Deletes an input file from user's workspace
     *
     * @return ResponseRedirect|JSON
     */
    public function removeFile(Request $request)
    {
        $user_email = session('user_info.email');

        $form = $request->all();

        // INPUT FILTERING: Check form data
        $validator = Validator::make($form, ['workspace_file'  => 'required|int']);

        if ($validator->fails()) {
            $errorMessage = "Workspace file removal was requested without a workspace file id.";
            return $this->illegalActionResponse($errorMessage, 400);
        }

        // CONDITION: The file to remove should exist
        $file_record = $this->conditionChecker->validWorkspaceFileId($form['workspace_file'], $user_email);

        DB::beginTransaction();

        try {
            $file_record->delete();
            $filepath = $this->workspace_path . '/' . $user_email . '/' . $file_record->filename;
            unlink($filepath);
        } catch (\Exception $ex) {
            DB::rollback();
            return $this->unexpectedErrorResponse($ex->getMessage(), 'Unexpected error!');
        }

        DB::commit();

        return $this->okResponse('File removed from workspace successfully!');
    }

    /**
     * Deletes a number of input files from user's workspace
     *
     * @return ResponseRedirect|View|JSON
     */
    public function removeFiles(Request $request)
    {
        $user_email = session('user_info.email');

        $form = $request->all();

        // INPUT FILTERING: Check form data
        $validator = Validator::make($form, ['files_to_delete'  => 'required|array']);

        if ($validator->fails()) {
            $errorMessage = "Input files removal was requested but no IDs found";
            return $this->illegalActionResponse($errorMessage, 400);
        }

        $files = $form['files_to_delete'];
        foreach ($files as $file) {
            // Extract file ID from submitted form value
            $parts = explode('-', $file);
            $file_id = $parts[2];

            // CONDITION: The provided file ID should be valid
            $file_record = $this->conditionChecker->validWorkspaceFileId($file_id, $user_email);

            DB::beginTransaction();

            try {
                $file_record->delete();
                $filepath = $this->workspace_path . '/' . $user_email . '/' . $file_record->filename;

                unlink($filepath);
            } catch (\Exception $ex) {
                DB::rollback();
                return $this->unexpectedErrorResponse($ex->getMessage(), 'Some files could not be deleted!');
            }

            DB::commit();
        }

        return $this->okResponse('Files removed from workspace successfully!');
    }
}
