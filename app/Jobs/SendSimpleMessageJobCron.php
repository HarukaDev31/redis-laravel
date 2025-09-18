<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class SendSimpleMessageJobCron implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $apiUrl;
    private $table = 'contenedor_consolidado_cotizacion_crons';
    private $tableCotizacion = 'contenedor_consolidado_cotizacion';
    
    /** @var string */
    private $message;
    
    /** @var string */
    private $phoneNumberId;

    private $jobId;

    public function __construct(string $message, string $phoneNumberId = "51912705923@c.us", int $jobId)
    {
        $this->message = $message;
        $this->phoneNumberId = $phoneNumberId;
        $this->jobId = $jobId;
        $this->apiUrl = env('SELLS_API_URL') ; 
    }

    public function handle()
    {
        try {
            $idCotizacion = DB::table($this->table)->where('id', $this->jobId)->value('id_cotizacion');
            $cotizacion = DB::table($this->tableCotizacion)->where('id', $idCotizacion);
            
            if(!$cotizacion->exists()){
                Log::error('El id de cotizacion no existe en la tabla contenedor_consolidado_cotizacion', [
                    'phoneNumberId' => $this->phoneNumberId,
                    'message' => substr($this->message, 0, 50) . '...'
                ]);
                // Marcar como ejecutado aunque falle
                DB::table($this->table)->where('id', $this->jobId)->update([
                    'executed_at' => date('Y-m-d H:i:s'),
                    'status' => 'EXECUTED'
                ]);
                return;
            }
            
            $estadoCotizador = $cotizacion->value('estado_cotizador');
            if ($estadoCotizador != 'PENDIENTE') {
                Log::info('El estado del cotizador no es PENDIENTE, no se enviará el mensaje.', [
                    'phoneNumberId' => $this->phoneNumberId,
                    'message' => substr($this->message, 0, 50) . '...'
                ]);
                DB::table($this->table)->where('id', $this->jobId)->update([
                    'executed_at' => date('Y-m-d H:i:s'),
                    'status' => 'EXECUTED'
                ]);
                return;
            }
            
            // Enviar mensaje de WhatsApp
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post($this->apiUrl, [
                'mensaje' => $this->message,
                'numero' => $this->phoneNumberId
            ]);

            if ($response->failed()) {
                Log::error('Error al enviar mensaje de WhatsApp: ' . $response->body(), [
                    'phoneNumberId' => $this->phoneNumberId
                ]);
                // Marcar como ejecutado aunque falle WhatsApp
                DB::table($this->table)->where('id', $this->jobId)->update([
                    'executed_at' => date('Y-m-d H:i:s'),
                    'status' => 'EXECUTED'
                ]);
                return;
            }

            // WhatsApp se envió exitosamente
            Log::info('Mensaje de WhatsApp enviado exitosamente', [
                'phoneNumberId' => $this->phoneNumberId,
                'message' => substr($this->message, 0, 50) . '...'
            ]);
            
            // Marcar job como ejecutado
            DB::table($this->table)->where('id', $this->jobId)->update([
                'executed_at' => date('Y-m-d H:i:s'),
                'status' => 'EXECUTED'
            ]);
            
            // Actualizar estado de cotización
            $idCotizacion = DB::table($this->table)->where('id', $this->jobId)->value('id_cotizacion');
            DB::table($this->tableCotizacion)->where('id', $idCotizacion)
                ->where('estado_cotizador', 'PENDIENTE')
                ->update([
                    'estado_cotizador' => 'CONTACTADO'
                ]);
            
            return $response->json();
            
        } catch (\Exception $e) {
            Log::error('Error general en SendSimpleMessageJob: ' . $e->getMessage(), [
                'phoneNumberId' => $this->phoneNumberId,
                'error' => $e->getTraceAsString()
            ]);
            
            // Marcar como ejecutado aunque haya error general
            DB::table($this->table)->where('id', $this->jobId)->update([
                'executed_at' => date('Y-m-d H:i:s'),
                'status' => 'EXECUTED'
            ]);
        }
    }
    public function tags()
    {
        return ['send-simple-message-job', 'phoneNumberId:' . $this->phoneNumberId];
    }
}