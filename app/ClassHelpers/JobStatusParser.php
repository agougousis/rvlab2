<?php

namespace App\ClassHelpers;

/**
 * Handles subtasks related to defining the status of a submitted job
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class JobStatusParser
{
    /**
     * Parses the status file of job in order to identify the jos status
     *
     * @param string $statusFilePath
     * @param string $initialStatus
     * @return array
     */
    public static function parseStatusFile($statusFilePath, $initialStatus)
    {
        $status_info = file($statusFilePath);
        $status_parts = preg_split('/\s+/', $status_info[0]);
        $status_message = $status_parts[8];
        switch ($status_message) {
            case 'Q':
                return ['queued', null, null];
            case 'R':
                $started_at = $status_parts[3] . ' ' . $status_parts[4];
                return ['running', $started_at, null];
            case 'ended':
                $started_at = $status_parts[3] . ' ' . $status_parts[4];
                $completed_at = $status_parts[5] . ' ' . $status_parts[6];

                return ['completed', $started_at, $completed_at];
            case 'ended_PBS_ERROR':
                $started_at = $status_parts[3] . ' ' . $status_parts[4];
                $completed_at = $status_parts[5] . ' ' . $status_parts[6];

                return ['failed', $started_at, $completed_at];
        }

        return [$initialStatus, null, null];
    }

    /**
     * Detects the file that needs to be parsed in order to look for job
     * execution errors that may have happened.
     *
     * @param string $jobfunction
     * @param int $job_id
     * @return string
     */
    public static function outputFileToParse($jobfunction, $job_id)
    {
        switch ($jobfunction) {
            case 'bict':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_taxa2dist':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_postgres_taxa2dist':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_anosim':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_mantel':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_taxa2taxon':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_permanova':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_bioenv':
                $fileToParse = '/cmd_line_output.txt';
                break;
            case 'parallel_simper':
                $fileToParse = '/cmd_line_output.txt';
                break;
            default:
                $fileToParse = '/job' . $job_id . '.Rout';
        }

        return $fileToParse;
    }
}
