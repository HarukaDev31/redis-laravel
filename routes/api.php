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
    Route::post('/welcomeV2', [WhatsAppController::class, 'sendWelcomeV2']);
    Route::post('/data-item', [WhatsAppController::class, 'sendDataItem']);
    Route::post('/data-itemV2', [WhatsAppController::class, 'sendDataItemV2']);
    Route::post('/message', [WhatsAppController::class, 'sendMessage']);
    Route::post('/messageV2', [WhatsAppController::class, 'sendMessageV2']);

    Route::post('/message-ventas', [WhatsAppController::class, 'sendMessageVentas']);
    Route::post('/message-ventasV2', [WhatsAppController::class, 'sendMessageVentasV2']);
    Route::post('/message-curso', [WhatsAppController::class, 'sendMessageCurso']);
    Route::post('/message-cursoV2', [WhatsAppController::class, 'sendMessageCursoV2']);
    Route::post('/media', [WhatsAppController::class, 'sendMedia']);
    Route::post('/mediaV2', [WhatsAppController::class, 'sendMediaV2']);
    Route::post('/media-inspection', [WhatsAppController::class, 'sendMediaInspection']);
    Route::post('/media-inspectionV2', [WhatsAppController::class, 'sendMediaInspectionV2']);
});