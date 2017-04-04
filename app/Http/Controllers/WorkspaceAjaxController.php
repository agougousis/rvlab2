<?php

namespace App\Http\Controllers;

use Session;
use Response;
use Illuminate\Http\Request;
use App\Http\Controllers\CommonController;

class WorkspaceAjaxController extends CommonController
{
    private $workspace_path;
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
     * Calculate storage utilization for the logged in user
     *
     * @return Response
     */
    public function userStorageUtilization()
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
    public function changeTabStatus(Request $request)
    {
        if ($request->has('new_status')) {
            $new_status = $request->input('new_status');
            if ($new_status == 'open') {
                Session::put('workspace_tab_status', 'open');
            } else {
                Session::put('workspace_tab_status', 'closed');
            }
        }

        return Response::json(array(), 200);
    }

    /**
     * Retrieves the column names from a CSV file
     *
     * @param string $filename
     * @return JSON
     */
    public function convert2rTool($filename)
    {
        $userInfo = session('user_info');
        $user_workspace_path = $this->workspace_path . '/' . $userInfo['email'];
        $filepath = $user_workspace_path . '/' . basename($filename);

        if (file_exists($filepath)) {
            $lines_file = file($filepath);

            $header_values = explode(",", $lines_file[0]);
            $headers = array();

            foreach ($header_values as $value) {
                $headers[] = clean_csv_header($value);
            }

            $response = array(
                'headers' => $headers,
            );

            return Response::json($response, 200);
        } else {
            $this->logEvent("File could not be found.", "error");
            $response = array('message', 'File could not be found.');
            return Response::json($response, 500);
        }
    }
}
