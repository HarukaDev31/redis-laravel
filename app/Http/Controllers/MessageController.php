<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Jobs\SendWhatsAppMessageJob;
use Illuminate\Support\Facades\Log;

class MessageController extends Controller
{
    public function sendBatch(Request $request)
    {
        $validated = $request->validate([
            'messages' => 'required|array',
            'messages.*.content' => 'required|string',
            'messages.*.pdf_content' => 'sometimes|string',
            'messages.*.pdf_filename' => 'sometimes|string'
        ]);

        foreach ($validated['messages'] as $message) {
            SendWhatsAppMessageJob::dispatch($message);
        }

        return response()->json([
            'status' => 'success',
            'message' => 'Mensajes encolados para envío'
        ]);
    }
}