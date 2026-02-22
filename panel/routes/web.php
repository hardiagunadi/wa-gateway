<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GatewayController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\UserManagementController;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => redirect('/admin/login'))->name('login');
Route::get('/', fn () => redirect('/admin'));

Route::middleware('auth')->group(function () {
    Route::get('/api/status', [GatewayController::class, 'apiStatus'])->name('api.status');
    Route::get('/sessions', [GatewayController::class, 'listSessions'])->name('sessions.list');
    Route::get('/sessions/{session}/groups', [GatewayController::class, 'listGroups'])->name('sessions.groups');
    Route::get('/sessions/{session}/message-status', [GatewayController::class, 'messageStatuses'])->name('sessions.message_status');
    Route::post('/sessions/{session}/test-send', [GatewayController::class, 'testSendMessage'])->name('sessions.test_send');
    Route::post('/sessions/{session}/webhook-test', [GatewayController::class, 'webhookTest'])->name('sessions.webhook_test');
    Route::get('/devices/status', [GatewayController::class, 'deviceStatus'])->name('devices.status');
    Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
    Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
    Route::get('/users/{user}/edit', [UserManagementController::class, 'edit'])->name('users.edit');
    Route::post('/users/{user}', [UserManagementController::class, 'update'])->name('users.update');

    Route::get('/profile', [ProfileController::class, 'show'])->name('profile.show');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
});
