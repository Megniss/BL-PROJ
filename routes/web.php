<?php

use Illuminate\Support\Facades\Route;

// visas lapas atver Vue, izņemot /storage failus
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!storage/).*$');
