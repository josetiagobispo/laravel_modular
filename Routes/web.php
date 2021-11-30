<?php

Route::group(['prefix' => 'admin', 'as' =>'admin.', 'middleware' => 'auth'], function () {
    Route::resource('gerador', 'GeradorController');
});
