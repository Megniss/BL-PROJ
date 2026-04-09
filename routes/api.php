<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\ComplaintController;
use App\Http\Controllers\LanguageController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BlockController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\PasswordResetController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RatingController;
use App\Http\Controllers\SwapRequestController;
use App\Http\Controllers\UserController;
use Illuminate\Support\Facades\Route;

// public routes
Route::get('/languages', [LanguageController::class, 'index']);
Route::get('/translations/{code}', [LanguageController::class, 'translations']);

Route::get('/browse', [BookController::class, 'browse']);
Route::get('/stats', [BookController::class, 'stats']);
Route::get('/users', [UserController::class, 'search'])->middleware('throttle:30,1');
Route::get('/users/{user}', [UserController::class, 'show'])->middleware('throttle:60,1');

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:10,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:10,1');
Route::post('/forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1');
Route::post('/reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::get('/profile', [ProfileController::class, 'show']);
    Route::patch('/profile', [ProfileController::class, 'update']);
    Route::patch('/profile/privacy', [ProfileController::class, 'updatePrivacy']);
    Route::get('/profile/history', [ProfileController::class, 'history']);
    Route::get('/notifications', [ProfileController::class, 'notifications']);
    Route::post('/notifications/read-all', [ProfileController::class, 'markAllRead']);
    Route::patch('/notifications/{id}/read', [ProfileController::class, 'markRead']);

    Route::get('/books', [BookController::class, 'index']);
    Route::post('/books', [BookController::class, 'store']);
    Route::put('/books/{book}', [BookController::class, 'update']);
    Route::delete('/books/{book}', [BookController::class, 'destroy']);
    Route::post('/books/{book}/cover', [BookController::class, 'uploadCover']);
    Route::delete('/books/{book}/cover', [BookController::class, 'removeCover']);

    // unread-count must be before {user} or Laravel treats it as an id
    Route::get('/messages/unread-count', [MessageController::class, 'unreadCount']);
    Route::get('/messages', [MessageController::class, 'index']);
    Route::get('/messages/{user}', [MessageController::class, 'show']);
    Route::post('/messages', [MessageController::class, 'store'])->middleware('throttle:30,1');
    Route::put('/messages/{message}', [MessageController::class, 'update']);
    Route::delete('/messages/{message}', [MessageController::class, 'destroy']);

    Route::post('/ratings', [RatingController::class, 'store']);

    Route::get('/blocks', [BlockController::class, 'index']);
    Route::post('/blocks/{user}', [BlockController::class, 'store']);
    Route::delete('/blocks/{user}', [BlockController::class, 'destroy']);

    Route::post('/swap-requests', [SwapRequestController::class, 'store'])->middleware('throttle:20,1');
    Route::get('/swap-requests/incoming', [SwapRequestController::class, 'incoming']);
    Route::get('/swap-requests/outgoing', [SwapRequestController::class, 'outgoing']);
    Route::patch('/swap-requests/{swap}/accept', [SwapRequestController::class, 'accept']);
    Route::patch('/swap-requests/{swap}/decline', [SwapRequestController::class, 'decline']);
    Route::delete('/swap-requests/{swap}', [SwapRequestController::class, 'destroy']);

    Route::get('/complaints', [ComplaintController::class, 'index']);
    Route::post('/complaints', [ComplaintController::class, 'store']);
    Route::get('/complaints/{complaint}', [ComplaintController::class, 'show']);
    Route::post('/complaints/{complaint}/messages', [ComplaintController::class, 'addMessage']);
    Route::patch('/complaints/{complaint}/close', [ComplaintController::class, 'close']);

    Route::middleware('admin')->group(function () {
        Route::get('/admin/logs', [AdminController::class, 'logs']);
        Route::get('/admin/languages', [LanguageController::class, 'all']);
        Route::post('/admin/languages', [LanguageController::class, 'store']);
        Route::patch('/admin/languages/{code}', [LanguageController::class, 'update']);
        Route::patch('/admin/languages/{code}/deactivate', [LanguageController::class, 'deactivate']);
        Route::patch('/admin/languages/{code}/reactivate', [LanguageController::class, 'reactivate']);
        Route::get('/admin/translations/{code}', [LanguageController::class, 'getTranslations']);
        Route::post('/admin/translations/{code}', [LanguageController::class, 'saveTranslations']);
        Route::get('/admin/users', [AdminController::class, 'users']);
        Route::get('/admin/books', [AdminController::class, 'books']);
        Route::get('/admin/swaps', [AdminController::class, 'swaps']);
        Route::get('/admin/ratings', [AdminController::class, 'ratings']);
        Route::patch('/admin/users/{user}/block', [AdminController::class, 'blockUser']);
        Route::patch('/admin/users/{user}/unblock', [AdminController::class, 'unblockUser']);
        Route::patch('/admin/users/{user}/make-admin', [AdminController::class, 'makeAdmin']);
        Route::patch('/admin/users/{user}/remove-admin', [AdminController::class, 'removeAdmin']);
        Route::delete('/admin/books/{book}', [AdminController::class, 'deleteBook']);
        Route::patch('/admin/books/{book}/review', [AdminController::class, 'reviewBook']);
        Route::patch('/admin/books/{book}/unreview', [AdminController::class, 'unreviewBook']);
        Route::patch('/admin/swaps/{swap}/accept', [AdminController::class, 'acceptSwap']);
        Route::patch('/admin/swaps/{swap}/decline', [AdminController::class, 'declineSwap']);
        Route::delete('/admin/swaps/{swap}', [AdminController::class, 'deleteSwap']);
    });
});
