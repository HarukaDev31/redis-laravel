<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Log;

class WhatsAppController extends Controller
{
    public function sendWelcome(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'carga' => 'required|string',
            'phoneNumberId' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            \App\Jobs\SendWelcomeMessageJob::dispatch(
                $request->input('carga'),
                $request->input('phoneNumberId'),
                0// Valor por defecto de 0 si no se proporciona,
            )->delay(now()->addSeconds($request->input('sleep', 0)));

            return response()->json([
                'status' => 'success',
                'message' => 'Mensaje de bienvenida encolado'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sendWelcome: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function sendDataItem(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'fileContent' => 'required|string',
            'fileName' => 'required|string',
            'phoneNumberId' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Guardar archivo temporal
            $tempPath = tempnam(sys_get_temp_dir(), 'whatsapp_');
            file_put_contents($tempPath, base64_decode($request->input('fileContent')));

            \App\Jobs\SendDataItemJob::dispatch(
                $request->input('message'),
                $tempPath,
                $request->input('phoneNumberId'),
                0
            )->delay(now()->addSeconds($request->input('sleep', 0)));

            return response()->json([
                'status' => 'success',
                'message' => 'Datos con archivo encolados'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sendDataItem: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function sendMessage(Request $request)
    {
        Log::info('sendMessage', $request->all());
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'phoneNumberId' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            \App\Jobs\SendSimpleMessageJob::dispatch(
                $request->input('message'),
                $request->input('phoneNumberId'),
                0
            )->delay(now()->addSeconds($request->input('sleep', 0)));

            return response()->json([
                'status' => 'success',
                'message' => 'Mensaje simple encolado'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sendMessage: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
    public function sendMessageV2(Request $request)
    {
        Log::info('sendMessage', $request->all());
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'phoneNumberId' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            \App\Jobs\SendSimpleMessageJobV2::dispatch(
                $request->input('message'),
                $request->input('phoneNumberId'),
                0
            )->delay(now()->addSeconds($request->input('sleep', 0)));

            return response()->json([
                'status' => 'success',
                'message' => 'Mensaje simple encolado'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sendMessage: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
    public function sendMessageVentas(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'phoneNumberId' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            \App\Jobs\SendSimpleMessageJobV2::dispatch(
                $request->input('message'),
                $request->input('phoneNumberId'),
                0,
                'ventas'
            )->delay(now()->addSeconds(0));

            return response()->json([
                'status' => 'success',
                'message' => 'Mensaje simple encolado'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sendMessage: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
    public function sendMessageCurso(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'phoneNumberId' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            \App\Jobs\SendSimpleMessageJobV2::dispatch(
                $request->input('message'),
                $request->input('phoneNumberId'),
                0,
                'curso'
            )->delay(now()->addSeconds(0));

            return response()->json([
                'status' => 'success',
                'message' => 'Mensaje simple encolado'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sendMessage: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
    public function sendMedia(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fileContent' => 'required|string',
            'fileName' => 'required|string',
            'phoneNumberId' => 'required|string',
            'mimeType' => 'nullable|string',
            'message' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Obtener extensión del archivo original
            $fileName = $request->input('fileName');
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            
            // Guardar archivo temporal con extensión
            $tempPath = tempnam(sys_get_temp_dir(), 'whatsapp_') . ($extension ? '.' . $extension : '');
            file_put_contents($tempPath, base64_decode($request->input('fileContent')));

            \App\Jobs\SendMediaMessageJob::dispatch(
                $tempPath,
                $request->input('phoneNumberId'),
                $request->input('mimeType'),
                $request->input('message'),
                0,
                $fileName,
                $request->input('fromNumber')??'consolidado'
            )->delay(now()->addSeconds($request->input('sleep', 0)));

            return response()->json([
                'status' => 'success',
                'message' => 'Media encolada'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sendMedia: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
    public function sendMediaV2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fileContent' => 'required|string',
            'fileName' => 'required|string',
            'phoneNumberId' => 'required|string',
            'mimeType' => 'nullable|string',
            'message' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $fileName = $request->input('fileName');

            \App\Jobs\SendMediaMessageJobV2::dispatch(
                $request->input('fileContent'), // Pasar base64 directamente
                $request->input('phoneNumberId'),
                $request->input('mimeType'),
                $request->input('message'),
                0,
                $fileName,
                $request->input('fromNumber')??'consolidado'
            )->delay(now()->addSeconds($request->input('sleep', 0)));

            return response()->json([
                'status' => 'success',
                'message' => 'Media encolada'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sendMedia: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
    public function sendMediaInspection(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fileContent' => 'required|string',
            'fileName' => 'required|string',
            'phoneNumberId' => 'required|string',
            'mimeType' => 'nullable|string',
            'message' => 'nullable|string',
            'inspectionId' => 'required|integer'
        ]);
        
        if ($validator->fails()) {
            Log::error('Error en sendMediaInspection: ' . $validator->errors());
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Obtener extensión del archivo original
            $fileName = $request->input('fileName');
            $extension = pathinfo($fileName, PATHINFO_EXTENSION);
            
            // Guardar archivo temporal con extensión
            $tempPath = tempnam(sys_get_temp_dir(), 'whatsapp_') . ($extension ? '.' . $extension : '');
            file_put_contents($tempPath, base64_decode($request->input('fileContent')));

            \App\Jobs\SendMediaInspectionMessageJob::dispatch(
                $tempPath,
                $request->input('phoneNumberId'),
                $request->input('mimeType'),
                $request->input('message'),
                0,
                $request->input('inspectionId'),
                $fileName
            )->delay(now()->addSeconds(0));

            return response()->json([
                'status' => 'success',
                'message' => 'Media Inspeccion encolada'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sendMediaInspeccion: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function sendMediaInspectionV2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'fileContent' => 'required|url', // Cambiar a validación de URL
            'fileName' => 'required|string',
            'phoneNumberId' => 'required|string',
            'mimeType' => 'nullable|string',
            'message' => 'nullable|string',
            'inspectionId' => 'required|integer'
        ]);
        
        if ($validator->fails()) {
            Log::error('Error en sendMediaInspectionV2: ' . $validator->errors());
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // fileContent ahora es una URL, pasarla directamente al job
            $fileUrl = $request->input('fileContent');
            $fileName = $request->input('fileName');

            // El job manejará la descarga de la URL internamente
            \App\Jobs\SendMediaInspectionMessageJobV2::dispatch(
                $fileUrl, // Pasar la URL directamente
                $request->input('phoneNumberId'),
                $request->input('mimeType'),
                $request->input('message'),
                0,
                $request->input('inspectionId'),
                $fileName
            )->delay(now()->addSeconds(0));

            return response()->json([
                'status' => 'success',
                'message' => 'Media Inspeccion V2 encolada'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sendMediaInspeccionV2: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function sendWelcomeV2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'carga' => 'required|string',
            'phoneNumberId' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            \App\Jobs\SendWelcomeMessageJobV2::dispatch(
                $request->input('carga'),
                $request->input('phoneNumberId'),
                0
            )->delay(now()->addSeconds($request->input('sleep', 0)));

            return response()->json([
                'status' => 'success',
                'message' => 'Mensaje de bienvenida V2 encolado'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sendWelcomeV2: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function sendDataItemV2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'fileContent' => 'required|string',
            'fileName' => 'required|string',
            'phoneNumberId' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            // Guardar archivo temporal
            $tempPath = tempnam(sys_get_temp_dir(), 'whatsapp_');
            file_put_contents($tempPath, base64_decode($request->input('fileContent')));

            \App\Jobs\SendDataItemJobV2::dispatch(
                $request->input('message'),
                $tempPath,
                $request->input('phoneNumberId'),
                0
            )->delay(now()->addSeconds($request->input('sleep', 0)));

            return response()->json([
                'status' => 'success',
                'message' => 'Datos con archivo V2 encolados'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sendDataItemV2: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function sendMessageVentasV2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'phoneNumberId' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            \App\Jobs\SendSimpleMessageJobV2::dispatch(
                $request->input('message'),
                $request->input('phoneNumberId'),
                0,
                'ventas'
            )->delay(now()->addSeconds(0));

            return response()->json([
                'status' => 'success',
                'message' => 'Mensaje ventas V2 encolado'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sendMessageVentasV2: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }

    public function sendMessageCursoV2(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'message' => 'required|string',
            'phoneNumberId' => 'required|string'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => 'error',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            \App\Jobs\SendSimpleMessageJobV2::dispatch(
                $request->input('message'),
                $request->input('phoneNumberId'),
                0,
                'curso'
            )->delay(now()->addSeconds(0));

            return response()->json([
                'status' => 'success',
                'message' => 'Mensaje curso V2 encolado'
            ]);

        } catch (\Exception $e) {
            Log::error('Error en sendMessageCursoV2: ' . $e->getMessage());
            return response()->json([
                'status' => 'error',
                'message' => 'Error interno del servidor'
            ], 500);
        }
    }
}