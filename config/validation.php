<?php

return array(
    'upload_to_workspace'    =>  array(
        'local_files'   =>  'mimes:csv,txt,nwk|max:50000',
    ),
    'vliz_import'   =>  array(
        'token' =>  'required|string|max:100',
        'jobid' =>  'required|integer'
    )
);