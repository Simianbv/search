<?php

use Illuminate\Support\Facades\Route;

$prefix = rtrim(trim(config('search.prefix'), '/'), '/') ;

$prefix = $prefix == '/' || $prefix == '//' || $prefix == '' ? '' : '/' . $prefix . '/' ;

Route::group(['middleware' => ['api', 'introspect']], function () use ($prefix) {

    Route::get($prefix . 'filters/{model?}', 'Simianbv\Search\Http\FilterController@getFiltersByModel');

});


