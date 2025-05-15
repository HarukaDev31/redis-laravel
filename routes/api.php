<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\WhatsAppController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::prefix('whatsapp')->group(function () {
    Route::post('/welcome', [WhatsAppController::class, 'sendWelcome']);
    Route::post('/data-item', [WhatsAppController::class, 'sendDataItem']);
    Route::post('/message', [WhatsAppController::class, 'sendMessage']);
    Route::post('/media', [WhatsAppController::class, 'sendMedia']);
    Route::post('/media-inspection', [WhatsAppController::class, 'sendMediaInspection']);
});