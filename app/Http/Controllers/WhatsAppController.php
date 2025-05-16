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
                $request->input('sleep', 0) // Valor por defecto de 0 si no se proporciona,
            );

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
                $request->input('sleep', 0) 
            );

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
                $request->input('sleep', 0) 
            );

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
            // Guardar archivo temporal
            $tempPath = tempnam(sys_get_temp_dir(), 'whatsapp_');
            file_put_contents($tempPath, base64_decode($request->input('fileContent')));

            \App\Jobs\SendMediaMessageJob::dispatch(
                $tempPath,
                $request->input('phoneNumberId'),
                $request->input('mimeType'),
                $request->input('message'),
                $request->input('sleep', 0) 
            );

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
            // Guardar archivo temporal
            $tempPath = tempnam(sys_get_temp_dir(), 'whatsapp_');
            file_put_contents($tempPath, base64_decode($request->input('fileContent')));

            \App\Jobs\SendMediaInspectionMessageJob::dispatch(
                $tempPath,
                $request->input('phoneNumberId'),
                $request->input('mimeType'),
                $request->input('message'),
                $request->input('sleep', 0),
                $request->input('inspectionId') 
            );

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
}