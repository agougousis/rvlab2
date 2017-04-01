<?php

namespace App\ClassHelpers;

use Validator;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class UploadValidator
{
    /**
     * Validates that all the files that were sent to be add to user's workspace are valid.
     *
     * @param Request $request
     * @return array
     */
    public static function validate_uploaded_workspace_files(Request &$request)
    {
        $valid_files = [];

        try {
            if ($request->hasFile('local_files')) {
                $all_uploads = $request->file('local_files');

                // Make sure it really is an array
                if (!is_array($all_uploads)) {
                    $all_uploads = array($all_uploads);
                }

                $error_messages = array();

                // Loop through all uploaded files
                foreach ($all_uploads as $upload) {

                    list($is_valid, $errorMessage) = self::validate_uploaded_file($upload);
                    if ($is_valid) {
                        $valid_files[] = $upload;
                    } else {
                        $error_messages[] = $errorMessage;
                    }
                }
            }
        } catch (Exception $ex) {
            $this->log_event($ex->getMessage(), "error");
            return 'Unexpected error!';
        }

        return [$valid_files, $error_messages];
    }

    /**
     * Checks the validity of a single workspace uploaded file
     *
     * @param UploadedFile $file
     * @return array
     */
    private static function validate_uploaded_file(UploadedFile $file) {
        // Ignore array member if it's not an UploadedFile object, just to be extra safe
        if (!is_a($file, 'Symfony\Component\HttpFoundation\File\UploadedFile')) {
            return [false, ''];
        }

        // This checks for non-zero file size, UPLOAD_ERR_OK and
        // if the file is really an uploaded file.
        if ((!$file->isValid()) || (filesize($file->getPathname()) == 0)) {
            $error_message = $file->getClientOriginalName().': Failed to upload or zero file size!';
            return [false, $error_message];
        }

        $parts = pathinfo($file->getClientOriginalName());
        $filename = $parts['basename'];
        $extension = $parts['extension'];

        $validator = Validator::make(
                        array(
                    'file' => $file,
                    'filename' => $filename, //$upload->getClientOriginalName(),
                    'extension' => $extension, //$upload->guessExtension(),
                        ), array(
                    'file' => 'max:50000',
                    'filename' => 'max:200',
                    'extension' => 'in:txt,csv,nwk',
                        )
        );

        if ($validator->fails()) {
            // Collect error messages
            if (!empty($validator->messages()->first('file'))){
                $error_message = $file->getClientOriginalName() . ':' . $validator->messages()->first('file');
                return [false, $error_message];
            }

            if (!empty($validator->messages()->first('filename'))) {
                $error_message = $file->getClientOriginalName() . ':' . $validator->messages()->first('filename');
                return [false, $error_message];
            }

            if (!empty($validator->messages()->first('extension'))) {
                $error_message = $file->getClientOriginalName() . ':' . $validator->messages()->first('extension');
                return [false, $error_message];
            }
        }

        return [true, ''];
    }
}
