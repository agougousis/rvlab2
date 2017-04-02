<?php


namespace App\ClassHelpers;

use App\Models\WorkspaceFile;
use App\ClassHelpers\WorkspaceHelper;
use App\Exceptions\InvalidRequestException;
use App\Exceptions\UnexpectedErrorException;

/**
 * Tests conditions that may be needed by controller methods
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class ConditionsChecker
{
    private $workspace_path;
    private $jobs_path;

    public function __construct($jobs_path, $workspace_path)
    {
        $this->workspace_path = $workspace_path;
        $this->jobs_path = $jobs_path;
    }

    /**
     * Tests if a specific user has in his workspace a specific filename.
     *
     * @param string $filename
     * @param string $user_email
     * @return string
     * @throws InvalidRequestException
     */
    public function workspaceFileBelongsToUser($filename, $user_email)
    {
        // The file should exist in database and owned ny this user
        $count_records = WorkspaceFile::where('user_email', $user_email)
                ->where('filename', $filename)
                ->count();

        if ($count_records == 0) {
            $exception = new InvalidRequestException('File '.$filename.' does not belong to user '.$user_email);
            $exception->setUserMessage('The filename you provided was not found in your workspace.');
            $exception->enableToastr();
            throw $exception;
        }

        // The file should also exist in filesystem
        $user_workspace_path = $this->workspace_path . '/' . $user_email;
        $filepath = $user_workspace_path . '/' . $filename;

        if (!file_exists($filepath)) {
            $exception = new UnexpectedErrorException("A workspace file is mentioned in database but does not exist in filesystem. File path = " . $filepath);
            $exception->setUserMessage('Something went wrong! The filename you provided could not be found.');
            throw $exception;
        }

        return $filepath;
    }

    /**
     * Tests if a file path, related to a job output, exists
     *
     * @param string $user_email
     * @param string $job_id
     * @param string $output_filename
     * @return string
     * @throws InvalidRequestException
     */
    public function outputFilepathExists($user_email, $job_id, $output_filename)
    {
        $job_folder = $this->jobs_path . '/' . $user_email . '/job' . $job_id;
        $filepath = $job_folder . '/' . $output_filename;

        if (!file_exists($filepath)) {
            $exception = new InvalidRequestException("Job output file was found at $filepath");
            $exception->setUserMessage('File could not be found.');
            throw $exception;
        }

        return $filepath;
    }

    /**
     * Tests if file path, related to a user's workspace, exists
     *
     * @param type $filepath
     * @throws InvalidRequestException
     */
    public function workspaceFilenameIsNotTaken($filepath)
    {
        if (!file_exists($filepath)) {
            $exception = new InvalidRequestException("File $filepath already exists.");
            $exception->setUserMessage('A file with such a name already exists in your workspace.');
            $exception->proposedHttpCode(428);
            throw $exception;
        }
    }

    /**
     * Checks if a workspace file with specific ID exists
     *
     * @param int $file_id
     * @return array An array in the form of:  [WorkspaceFile $file_record, string $errorMessage, int $errorStatus]
     */
    public function validWorkspaceFileId($file_id, $user_email)
    {
        if (!is_numeric($file_id)) {
            $exception = new InvalidRequestException('An invalid workspace file ID was given.');
            $exception->setUserMessage('Invalid request! Workspace file could not be found.');
            throw $exception;
        }

        // Retrieve file information
        $file_record = WorkspaceFile::where('id', $file_id)
                ->where('user_email', $user_email)
                ->first();

        // Check that file record is not empty
        if (empty($file_record)) {
            $exception = new InvalidRequestException('Workspace file removal was requested with an illegal workspace file id.');
            $exception->setUserMessage('Invalid request! File to be deleted was not found.');
            throw $exception;
        }

        // Check that file exists in the filesystem
        $filepath = $this->workspace_path . '/' . $user_email . '/' . $file_record->filename;
        if (!file_exists($filepath)) {
            $exception = new UnexpectedErrorException("Workspace file to be deleted could not be found in the file system.");
            $exception->setUserMessage('Something went wrong! File deletion failed.');
            throw $exception;
        }

        return $file_record;
    }
}
