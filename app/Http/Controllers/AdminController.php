<?php

namespace App\Http\Controllers;

use Session;
use Redirect;
use App\Models\Job;
use App\Models\JobsLog;
use App\Models\Setting;
use App\Models\SystemLog;
use App\Models\Registration;
use App\Models\WorkspaceFile;
use App\Presenters\StorageUtilization;
use App\Http\Controllers\CommonController;
use Illuminate\Http\Request;

/**
 * Handles the administration functionality of R vLab
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class AdminController extends CommonController
{
    /**
     * Loads the administration control panel
     *
     * @return View
     */
    public function index()
    {
        return $this->loadView('admin.index', 'Admin Pages');
    }

    /**
     * Loads the system configuration page
     *
     * @return View
     */
    public function configure()
    {
        $settings = Setting::all();
        $data['settings'] = $settings;

        return $this->loadView('admin.configure', 'System Configuration', $data);
    }

    /**
     * Saves the new system configuration
     *
     * @param Request $request
     * @return RedirectResponse
     */
    public function saveConfiguration(Request $request)
    {
        // Validate Input
        $validation_rules = config('validation.store_settings');
        $this->validate($request, $validation_rules);

        // Update Settings
        $form = filter_request($request, array_keys($validation_rules));
        Setting::updateAll($form);

        Session::flash('toastr', array('success', 'The settings have been updated!'));
        return Redirect::to('admin/configure');
    }

    /**
     * Loads R vLab usage statistics page
     *
     * @return View
     */
    public function statistics()
    {
        $year = date('Y');
        $prev_year = $year - 1;
        $month = date('n');
        $day = date('j');

        if ($day < 15) {
            $month--;
        }

        $startFromDate = "$prev_year-$month-$day 00:00:00";

        $data['registration_counts'] = $this->registrationStats($year, $prev_year, $month, $day);
        $data['f_stats'] = $this->functionStats($startFromDate);
        $data['s_stats'] = $this->jobSizeStats($startFromDate);

        return $this->loadView('admin.statistics', 'R vLab Usage Statistics', $data);
    }

    /**
     * Calculates statistics about the size of jobs submitted in the past
     *
     * @param string $startFromDate
     * @return array
     */
    protected function jobSizeStats($startFromDate)
    {
        $s_stats = array(
            '1' => 0,
            '2' => 0,
            '3' => 0,
            '4' => 0,
            '5' => 0,
            '6' => 0,
            '7' => 0,
            '8' => 0,
            '9' => 0
        );

        // We just count how many jobs are there with jobsize of x digits
        // (how many from 1-9 KB, how many from 10-99 KB, etc)
        $size_stats = JobsLog::countBySizeScale($startFromDate);

        foreach ($size_stats as $stat) {
            $s_stats[$stat['digits']] = $stat['total'];
        }

        return $s_stats;
    }

    /**
     * Calculates statistics about the type of jobs/analysis submitted in the past
     *
     * @param string $startFromDate
     * @return array
     */
    protected function functionStats($startFromDate)
    {
        $f_stats = JobsLog::countFunctionUsage($startFromDate);

        return $f_stats;
    }

    /**
     * Calculates statistics about registrations
     *
     * @param string $year
     * @param string $prev_year
     * @param string $month
     * @param string $day
     * @return array
     */
    protected function registrationStats($year, $prev_year, $month, $day)
    {
        $limits = array(
            array('Jan', '01-01 00:00:00', '01-15 23:59:59'),
            array('Feb', '02-01 00:00:00', '02-15 23:59:59'),
            array('Mar', '03-01 00:00:00', '03-15 23:59:59'),
            array('Apr', '04-01 00:00:00', '04-15 23:59:59'),
            array('May', '05-01 00:00:00', '05-15 23:59:59'),
            array('Jun', '06-01 00:00:00', '06-15 23:59:59'),
            array('Jul', '07-01 00:00:00', '07-15 23:59:59'),
            array('Aug', '08-01 00:00:00', '08-15 23:59:59'),
            array('Sep', '09-01 00:00:00', '09-15 23:59:59'),
            array('Oct', '10-01 00:00:00', '10-15 23:59:59'),
            array('Nov', '11-01 00:00:00', '11-15 23:59:59'),
            array('Dec', '12-01 00:00:00', '12-15 23:59:59'),
        );

        $counts = array();

        // From last year's same month to last year's end
        for ($j = $month; $j < 12; $j++) {
            // Get the middle day of this months
            $checkpoint = $prev_year . "-" . $limits[$j][2];
            // Get registrations that were active during this day
            $regs = Registration::where('starts', '<=', $checkpoint)
                    ->where('ends', '>=', $checkpoint)
                    ->count();
            // Store month label alogside the counted registrations
            $counts[] = array($limits[$j][0] . " " . $prev_year, $regs);
        }

        // From this year's start to previous month
        for ($j = 0; $j < $month; $j++) {
            // Get the middle day of this months
            $checkpoint = $year . "-" . $limits[$j][2];
            // Get registrations that were active during this day
            $regs = Registration::where('starts', '<=', $checkpoint)
                    ->where('ends', '>=', $checkpoint)
                    ->count();
            // Store month label alogside the counted registrations
            $counts[] = array($limits[$j][0] . " " . $year, $regs);
        }

        return $counts;
    }

    /**
     * Displays the last 50 jobs submitted to R vLab
     *
     * @return View
     */
    public function jobList()
    {
        $data['job_list'] = Job::getLastN(50);
        return $this->loadView('admin.job_list', 'Last Jobs List', $data);
    }

    /**
     * Displays the last 20 errors logged by R vLab
     *
     * @return View
     */
    public function lastErrors()
    {
        $last_error_count_setting = Setting::where('sname', 'last_errors_to_display')->first();
        $error_list = SystemLog::getLastErrors($last_error_count_setting);

        $data['error_list'] = $error_list;
        return $this->loadView('admin.last_errors', 'Last errors list', $data);
    }

    /**
     * Displays storate utilization information for each R vLab user.
     *
     * @return View
     */
    public function storageUtilization()
    {
        // Get configuration
        $rvlab_storage_limit = $this->system_settings['rvlab_storage_limit'];
        $max_users_supported = $this->system_settings['max_users_supported'];

        $jobs_path = config('rvlab.jobs_path');
        $workspace_path = config('rvlab.workspace_path');

        // Calculate Total Storage Utilization
        $jobs_size = directory_size($jobs_path); // in KB
        $workspace_size = directory_size($workspace_path); // in KB

        $used_size = $jobs_size + $workspace_size;

        // Calculate per user storage utilization
        list($workspace_totals, $jobspace_totals, $user_totals) = $this->storageUsedByUsers($workspace_path, $jobs_path);

        // Pass data to presenter
        $storagePresenter = new StorageUtilization($used_size, $user_totals);
        $storagePresenter->utilization = 100 * $used_size / $rvlab_storage_limit;
        $storagePresenter->user_soft_limit = $rvlab_storage_limit / $max_users_supported; // in KB
        $storagePresenter->rvlab_storage_limit = $rvlab_storage_limit;
        $storagePresenter->max_users_supported = $max_users_supported;

        return $this->loadView('admin.storage_utilization', 'Storage Utilization', ['storage' => $storagePresenter]);
    }

    /**
     * Returns information about the storage that is being used by users (in KB)
     *
     * @param string $workspace_path
     * @param string $jobs_path
     * @return array
     */
    protected function storageUsedByUsers($workspace_path, $jobs_path)
    {
        // Storage Utilization per User (A - input files)
        $inputs_users = WorkspaceFile::select('user_email')->distinct()->get(); // Get users with a least one input file

        $user_totals = [];
        $workspace_totals = [];

        foreach ($inputs_users as $user) {
            $workspace_totals[$user->user_email] = directory_size($workspace_path . '/' . $user->user_email); // in KB
            $user_totals[$user->user_email] = $workspace_totals[$user->user_email];
        }

        // Storage Utilization per User (B - jobs)
        $rvlab_users = Job::select('user_email')->distinct()->get(); // Get users with a least one job
        $jobspace_totals = array();
        foreach ($rvlab_users as $user) {
            $jobspace_totals[$user->user_email] = directory_size($jobs_path . '/' . $user->user_email); // in KB
            if (isset($user_totals[$user->user_email])) {
                $user_totals[$user->user_email] += $jobspace_totals[$user->user_email];
            } else {
                $user_totals[$user->user_email] = $jobspace_totals[$user->user_email];
            }
        }

        return [$workspace_totals, $jobspace_totals, $user_totals];
    }
}
