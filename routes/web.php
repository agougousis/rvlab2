<?php

// Home Page
Route::get('/', 'JobController@index');

// Login/logout Routes (in case the default LocalAuthenticator is used)
Route::get('/rlogin', 'DefaultLoginController@login_page');
Route::post('/rlogin', 'DefaultLoginController@login');
Route::get('/logout', 'DefaultLoginController@logout');

// Job page
Route::get('/job/{job_id}', 'JobController@job_page');

// New job submission
Route::post('/job', 'JobController@submit');
Route::post('/job/parallel','ParallelController@submit');
Route::post('/job/serial', 'SerialController@submit');
Route::post('/job/visual', 'VisualController@submit');

// Delete a Job
Route::post('/job/delete/{job_id}', 'JobController@delete_job');
Route::post('/job/delete_many', 'JobController@delete_many_jobs');
// Get a file
Route::get('/storage/get_job_file/job/{job_id}/{filename}', 'JobController@get_job_file');
// Get R script
Route::get('/storage/get_r_script/{job_id}', 'JobController@get_r_script');
// Get user jobs status (to be called periodically)
Route::get('/get_user_jobs', 'JobController@get_user_jobs');
// Get single job status (to be called periodically)
Route::get('/get_job_status/{job_id}', 'JobController@get_job_status');

// Workspace Routes
Route::get('/workspace/get/{filename}', 'WorkspaceController@get_file');
Route::get('/workspace/manage', 'WorkspaceController@manage');
Route::post('/workspace/add_files', 'WorkspaceController@add_files');
Route::post('/workspace/remove_file', 'WorkspaceController@remove_file');
Route::post('/workspace/remove_files', 'WorkspaceController@remove_files');
Route::post('/workspace/add_output_file', 'WorkspaceController@add_output_file');
Route::post('/workspace/add_example_data', 'WorkspaceController@add_example_data');

Route::get('/workspace/convert2r/{filename}', 'WorkspaceController@convert2r_tool');
Route::post('/workspace/tab_status', 'WorkspaceController@change_tab_status');

Route::get('/workspace/user_storage_utilization', 'WorkspaceController@user_storage_utilization');

Route::post('/workspace/vliz_import/{token}/{jobid}', 'WorkspaceController@vliz_import');
// Help Routes
Route::get('/help/documentation/{function}', 'HelpController@documentation');
Route::get('/help/storage_policy', 'HelpController@policy');
Route::get('/help/technical_documentation', 'HelpController@technical_docs');
Route::get('/help/video', 'HelpController@video');

// Registration
Route::get('/registration', 'RegistrationController@registration_page');
Route::post('/registration', 'RegistrationController@register');

// Mobile
Route::get('/get_token', 'AuthController@get_token');
Route::get('/mobile/forms/{function}', 'MobileController@forms');
