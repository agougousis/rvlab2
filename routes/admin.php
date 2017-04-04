<?php

// Administrative Routes
Route::get('/admin', 'AdminController@index');
Route::get('/admin/job_list', 'AdminController@jobList');
Route::get('/admin/last_errors', 'AdminController@lastErrors');
Route::get('/admin/storage_utilization', 'AdminController@storageUtilization');
Route::get('/admin/statistics', 'AdminController@statistics');
Route::get('/admin/configure', 'AdminController@configure');
Route::post('admin/configure', 'AdminController@saveConfiguration');