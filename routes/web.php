<?php

use App\Http\Controllers\DigestController;
use App\Models\User;
use App\Notifications\NewMessage;
use Illuminate\Support\Facades\Route;

// atrakstīšanās no iknedēļas apkopojuma (saite e-pastā ir parakstīta)
Route::get('/email/unsubscribe/{user}', [DigestController::class, 'unsubscribe'])
    ->middleware(['signed', 'throttle:20,1'])
    ->name('digest.unsubscribe');

// e-pastu priekšskatījums pārlūkā, tikai lokāli
if (app()->environment('local')) {
    Route::get('/dev/mail/digest', [DigestController::class, 'preview']);
    Route::get('/dev/mail/notification', function () {
        $user = User::firstOrFail();

        return (new NewMessage($user, 'Hi! Is the book still available? I could swap it for Fausts.'))->toMail($user);
    });
}

// visas lapas atver Vue, izņemot /storage failus
Route::get('/{any}', function () {
    return view('welcome');
})->where('any', '^(?!storage/).*$');
