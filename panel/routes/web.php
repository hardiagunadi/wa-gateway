<?php

use App\Http\Controllers\GatewayController;
use Illuminate\Support\Facades\Route;

Route::get('/', [GatewayController::class, 'index'])->name('dashboard');
Route::post('/sessions/start', [GatewayController::class, 'startSession'])->name('sessions.start');
Route::post('/sessions/{session}/close', [GatewayController::class, 'closeSession'])->name('sessions.close');
Route::post('/webhook', [GatewayController::class, 'updateWebhook'])->name('webhook.update');
Route::post('/server/start', [GatewayController::class, 'startServer'])->name('server.start');
Route::post('/server/stop', [GatewayController::class, 'stopServer'])->name('server.stop');
