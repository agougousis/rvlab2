<?php

namespace App\ClassHelpers;

use App\Models\WorkspaceFile;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Helper class for actions related to user's workspace
 *
 * @license MIT
 * @author Alexandros Gougousis <alexandros.gougousis@gmail.com>
 */
class WorkspaceHelper
{
    private $user_email;
    private $workspace_path;

    public function __construct($user_email, $workspace_path)
    {
        $this->user_email = $user_email;
        $this->workspace_path = $workspace_path;
    }

    /**
     * Copies an example file to user's workspace
     *
     * @param string $filename
     */
    public function moveExampleToWorkspace($filename)
    {
        $user_workspace_path = $this->workspace_path . '/' . $this->user_email;

        $source = public_path()."/files/$filename";
        $destination = "$user_workspace_path/$filename";

        if (!file_exists($destination)) {
            copy($source, $destination);

            $workspace_file = new WorkspaceFile();
            $workspace_file->user_email = $this->user_email;
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
        $workspace_file->user_email = $this->user_email;
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
