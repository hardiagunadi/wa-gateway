<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GatewayController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->name('login.attempt');
    Route::get('/forgot-password', [AuthController::class, 'showForgotPassword'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendResetPassword'])->name('password.reset');
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
    Route::get('/sessions/{session}/groups', [GatewayController::class, 'listGroups'])->name('sessions.groups');
    Route::get('/sessions/{session}/message-status', [GatewayController::class, 'messageStatuses'])->name('sessions.message_status');
    Route::post('/sessions/{session}/test-send', [GatewayController::class, 'testSendMessage'])->name('sessions.test_send');
    Route::get('/devices/manage', [GatewayController::class, 'deviceManagement'])->name('devices.manage');
    Route::post('/devices', [GatewayController::class, 'createDevice'])->name('devices.create');
    Route::post('/devices/create-json', [GatewayController::class, 'createDeviceJson'])->name('devices.create_json');
    Route::post('/devices/{device}/delete', [GatewayController::class, 'deleteDevice'])->name('devices.delete');
    Route::post('/gateway/base', [GatewayController::class, 'updateGatewayBase'])->name('gateway.update_base');
    Route::post('/gateway/reset-sessions', [GatewayController::class, 'updatePasswordResetSessions'])->name('gateway.update_reset_sessions');
    Route::get('/devices/status', [GatewayController::class, 'deviceStatus'])->name('devices.status');
    Route::post('/server/start', [GatewayController::class, 'startServer'])->name('server.start');
    Route::post('/server/stop', [GatewayController::class, 'stopServer'])->name('server.stop');

    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::post('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
});
