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
    Route::get('/api/status', [GatewayController::class, 'apiStatus'])->name('api.status');
    Route::get('/sessions', [GatewayController::class, 'listSessions'])->name('sessions.list');
    Route::post('/sessions/start', [GatewayController::class, 'startSession'])->name('sessions.start');
    Route::post('/sessions/restart', [GatewayController::class, 'restartSession'])->name('sessions.restart');
    Route::post('/sessions/{session}/close', [GatewayController::class, 'closeSession'])->name('sessions.close');
    Route::post('/sessions/{session}/config', [GatewayController::class, 'saveSessionConfig'])->name('sessions.config');
    Route::post('/sessions/{session}/webhook-test', [GatewayController::class, 'webhookTest'])->name('sessions.webhook_test');
    Route::post('/devices', [GatewayController::class, 'createDevice'])->name('devices.create');
    Route::post('/devices/{device}/delete', [GatewayController::class, 'deleteDevice'])->name('devices.delete');
    Route::get('/devices/status', [GatewayController::class, 'deviceStatus'])->name('devices.status');
    Route::post('/server/start', [GatewayController::class, 'startServer'])->name('server.start');
    Route::post('/server/stop', [GatewayController::class, 'stopServer'])->name('server.stop');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
});
