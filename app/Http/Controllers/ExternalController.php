<?php

namespace App\Http\Controllers;

use DB;
use Response;
use Validator;
use App\Models\WorkspaceFile;
use App\Http\Controllers\CommonController;

/**
 * Handles the functionality related to importing and exporting files to user's workspace.
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class ExternalController extends CommonController
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

    public function __construct()
    {
        parent::__construct();
        $this->workspace_path = config('rvlab.workspace_path');
        $this->jobs_path = config('rvlab.jobs_path');

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
     * Import to workspace for analysis a file from VLIZ Lifewatch
     *
     * @param string $token
     * @param string $jobid
     * @return Response
     */
    public function vlizImport($token, $jobid)
    {
        $cors_headers = [
            'Access-Control-Allow-Origin' => 'http://www.lifewatch.be',
            'Access-Control-Allow-Methods' => "POST",
            'Access-Control-Allow-Credentials' => 'true'
        ];

        // Validate the URL parameters
        $params = compact('token', 'jobid');
        $rules = config('validation.vliz_import');
        $validator = Validator::make($params, $rules);
        if ($validator->fails()) {
            $errorMessage = "";
            foreach ($validator->errors()->getMessages() as $key => $errorMessages) {
                foreach ($errorMessages as $msg) {
                    $errorMessage .= "$key: $msg,";
                }
                trim($errorMessage, ',');
            }
            return response()->json([
                        'status' => 'failed',
                        'message' => $errorMessage
                            ], 200, $cors_headers);
        }

        DB::beginTransaction();
        try {
            // Build the URL that will be used for file retrieval
            $vliz_file_url = "http://www.lifewatch.be/data-services/passfile.php?filetype=resultfile&token=$token&jobid=$jobid";

            // Retrieve the file
            $client = new \Guzzle\Service\Client($vliz_file_url);
            $request = $client->get();
            $response = $request->send();

            // Check the status code
            $statusCode = $response->getStatusCode();
            if ($statusCode != 200) {
                DB::rollBack(); // Though nothing has been written to DB, it has to close the transaction.
                $reason = $response->getReasonPhrase();
                // Responde to VLIZ
                return Response::json([
                            'status' => 'failed',
                            'message' => "Failed to retrieve file from $vliz_file_url , status code = " . $statusCode . " , reason = " . $reason
                                ], 200, $cors_headers);
            } else {
                $file_content = $response->getBody();

                // If the user workspace folder does not exist, create it
                $user_email = $this->user_status['email'];
                $user_workspace_path = $this->workspace_path . '/' . $user_email;
                if (!file_exists($user_workspace_path)) { // just in case
                    mkdir($user_workspace_path);
                }

                // Build the destination file path
                $remote_filename = "vliz_" . $jobid . ".txt";
                $new_filepath = $user_workspace_path . '/' . $remote_filename;

                // Check if the file already exists
                if (!file_exists($new_filepath)) {
                    // Save the contents to file
                    file_put_contents($new_filepath, $file_content);

                    // Add a record to database
                    $workspace_file = new WorkspaceFile();
                    $workspace_file->user_email = $user_email;
                    $workspace_file->filename = $remote_filename;
                    $workspace_file->filesize = filesize($new_filepath);
                    $workspace_file->save();
                } else {
                    DB::rollBack(); // Though nothing has been written to DB, it has to close the transaction.
                    // Responde to VLIZ
                    return Response::json([
                                'status' => 'failed',
                                'message' => "A file with the same filename exists in user's R vLab workspace. It is possible that this file has been imported again in the past."
                                    ], 200, $cors_headers);
                }
            }
        } catch (\Exception $ex) {
            DB::rollBack();

            // Log the exception
            $this->logEvent('File import from VLIZ failed! Error: ' . $ex->getMessage(), 'error');

            // Delete file if created
            if (file_exists($new_filepath)) {
                unlink($new_filepath);
            }

            // Responde to VLIZ
            return Response::json([
                        'status' => 'failed',
                        'message' => 'File import failed for unknown reason! Check R vLab logs.'
                            ], 200, $cors_headers);
        }

        DB::commit();

        // Responde to VLIZ
        return Response::json([
                    'status' => 'imported',
                    'message' => ''
                        ], 200, $cors_headers);
    }
}
