<?php

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

    // unread-count pirms {user} — citādi Laravel domā ka tas ir id
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
});
