<?php

use App\Http\Controllers\DigestController;
use App\Http\Controllers\EmailChangeController;
use App\Models\User;
use App\Notifications\NewMessage;
use App\Support\Digest;
use Illuminate\Support\Facades\Route;

// atrakstīšanās no viena e-pasta veida (saite e-pastā ir parakstīta)
Route::get('/email/unsubscribe/{user}/{type}', [DigestController::class, 'unsubscribe'])
    ->middleware(['signed', 'throttle:20,1'])
    ->whereIn('type', Digest::TYPES)
    ->name('digest.unsubscribe');

// jaunā e-pasta apstiprināšana (saite e-pastā ir parakstīta un der 60 min)
Route::get('/email/confirm/{user}', [EmailChangeController::class, 'confirm'])
    ->middleware(['signed', 'throttle:20,1'])
    ->name('email.confirm');

// e-pastu priekšskatījums pārlūkā, tikai lokāli
if (app()->environment('local')) {
    Route::get('/dev/mail/digest/{type?}', [DigestController::class, 'preview'])->whereIn('type', Digest::TYPES);
    Route::get('/dev/mail/notification', function () {
        $user = User::firstOrFail();

        return (new NewMessage($user, 'Hi! Is the book still available? I could swap it for Fausts.'))->toMail($user);
    });
}

// visas lapas atver Vue, izņemot /storage failus
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!storage/).*$');
