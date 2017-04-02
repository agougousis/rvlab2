<?php

namespace App\ClassHelpers;

use App\Models\WorkspaceFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Description of WorkspaceHelper
 *
 * @author Alexandros
 */
class WorkspaceHelper
{
    private $userInfo;
    private $workspace_path;

    public function __construct($userInfo, $workspace_path)
    {
        $this->userInfo = $userInfo;
        $this->workspace_path = $workspace_path;
    }

    /**
     * Checks if a workspace file with specific ID exists
     *
     * @param int $file_id
     * @return array An array in the form of:  [WorkspaceFile $file_record, string $errorMessage, int $errorStatus]
     */
    public function fileExists($file_id)
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
                ->where('user_email', $this->userInfo['email'])
                ->first();

        // Check that file record is not empty
        if (empty($file_record)) {
            return [null, "Workspace file removal was requested with an illegal workspace file id.", 400];
        }

        // Check that file exists in the filesystem
        $filepath = $this->workspace_path . '/' . $this->userInfo['email'] . '/' . $file_record->filename;
        if (!file_exists($filepath)) {
            return [null, "Workspace file could not be found in the file system.", 500];
        }

        return [$file_record, '', null];
    }

    /**
     * Copies an example file to user's workspace
     *
     * @param string $filename
     */
    public function moveExampleToWorkspace($filename)
    {
        $user_workspace_path = $this->workspace_path . '/' . $this->userInfo['email'];

        $source = public_path()."/files/$filename";
        $destination = "$user_workspace_path/$filename";

        if (!file_exists($destination)) {
            copy($source, $destination);

            $workspace_file = new WorkspaceFile();
            $workspace_file->user_email = $this->userInfo['email'];
            $workspace_file->filename = $filename;
            $workspace_file->filesize = filesize($source);
            $workspace_file->save();
        }
    }

    /**
     * Moves an uploaded file to user's workspace
     *
     * @param UploadedFile $file
     * @param string $destinationFilePath
     */
    public function moveUploadedToWorkspace(UploadedFile $file, $destinationFilePath)
    {
        $remote_filename = safe_filename($file->getClientOriginalName());
        $sourceFilePath = $file->getPath() . '/' . $file->getBasename();

        // Add a record to database
        $workspace_file = new WorkspaceFile();
        $workspace_file->user_email = $this->userInfo['email'];
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
}
