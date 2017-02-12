<?php

// Administrative Routes
Route::get('/admin', 'AdminController@index');
Route::get('/admin/job_list', 'AdminController@job_list');
Route::get('/admin/last_errors', 'AdminController@last_errors');
Route::get('/admin/storage_utilization', 'AdminController@storage_utilization');
Route::get('/admin/statistics', 'AdminController@statistics');
Route::get('/admin/configure', 'AdminController@configure');
Route::post('admin/configure', 'AdminController@save_configuration');