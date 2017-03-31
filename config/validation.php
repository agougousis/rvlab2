<?php

return array(
    'upload_to_workspace'    =>  [
        'local_files'   =>  'mimes:csv,txt,nwk|max:50000',
    ],
    'vliz_import'   =>  [
        'token' =>  'required|string|max:100',
        'jobid' =>  'required|integer'
    ],
    'store_settings'    =>  [
        'rvlab_storage_limit'   => 'alpha_dash|max:100',
        'max_users_supported'   => 'alpha_dash|max:100',
        'job_max_storagetime'   => 'alpha_dash|max:100',
        'status_refresh_rate_page'    => 'alpha_dash|max:100'
    ]
);