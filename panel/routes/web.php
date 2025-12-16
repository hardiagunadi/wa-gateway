<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GatewayController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
});

Route::post('/logout', [AuthController::class, 'logout'])->name('logout')->middleware('auth');

Route::middleware('auth')->group(function () {
    Route::get('/', [GatewayController::class, 'index'])->name('dashboard');
    Route::post('/sessions/start', [GatewayController::class, 'startSession'])->name('sessions.start');
    Route::post('/sessions/{session}/close', [GatewayController::class, 'closeSession'])->name('sessions.close');
    Route::post('/webhook', [GatewayController::class, 'updateWebhook'])->name('webhook.update');
    Route::post('/server/start', [GatewayController::class, 'startServer'])->name('server.start');
    Route::post('/server/stop', [GatewayController::class, 'stopServer'])->name('server.stop');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
});
