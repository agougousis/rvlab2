<?php

// Home Page
Route::get('/', 'JobController@index');

// Login/logout Routes (in case the default LocalAuthenticator is used)
Route::get('/rlogin', 'DefaultLoginController@loginPage');
Route::post('/rlogin', 'DefaultLoginController@login');
Route::get('/logout', 'DefaultLoginController@logout');

// Job page
Route::get('/job/{job_id}', 'ResultPageController@jobPage');

// New job submission
Route::post('/job','JobController@submit');

// Delete a Job
Route::post('/job/delete/{job_id}', 'JobController@deleteJob');
Route::post('/job/delete_many', 'JobController@deleteManyJobs');
// Get a file
Route::get('/storage/get_job_file/job/{job_id}/{filename}', 'JobController@getJobFile');
// Get R script
Route::get('/storage/get_r_script/{job_id}', 'JobController@getRScript');
// Get user jobs status (to be called periodically)
Route::get('/get_user_jobs', 'JobController@getUserJobs');
// Get single job status (to be called periodically)
Route::get('/get_job_status/{job_id}', 'JobController@getJobStatus');

// Workspace Routes
Route::get('/workspace/get/{filename}', 'WorkspaceController@getFile');
Route::get('/workspace/manage', 'WorkspaceController@manage');
Route::post('/workspace/add_files', 'WorkspaceController@addFiles');
Route::post('/workspace/remove_file', 'WorkspaceController@removeFile');
Route::post('/workspace/remove_files', 'WorkspaceController@removeFiles');
Route::post('/workspace/add_output_file', 'WorkspaceController@addOutputFile');
Route::post('/workspace/add_example_data', 'WorkspaceController@addExampleData');

Route::get('/workspace/convert2r/{filename}', 'WorkspaceAjaxController@convert2rTool');
Route::post('/workspace/tab_status', 'WorkspaceAjaxController@changeTabStatus');

Route::get('/workspace/user_storage_utilization', 'WorkspaceAjaxController@userStorageUtilization');

Route::post('/workspace/vliz_import/{token}/{jobid}', 'ExternalController@vlizImport');
// Help Routes
Route::get('/help/documentation/{function}', 'HelpController@documentation');
Route::get('/help/storage_policy', 'HelpController@policy');
Route::get('/help/technical_documentation', 'HelpController@technicalDocs');
Route::get('/help/video', 'HelpController@video');

// Registration
Route::get('/registration', 'RegistrationController@registrationPage');
Route::post('/registration', 'RegistrationController@register');

// Mobile
Route::get('/get_token', 'CommonController@getToken');
Route::get('/mobile/forms/{function}', 'MobileController@forms');
