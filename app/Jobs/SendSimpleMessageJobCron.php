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
                //delete job and log error 
                Log::error('El id de cotizacion no existe en la tabla contenedor_consolidado_cotizacion', [
                    'phoneNumberId' => $this->phoneNumberId,
                    'message' => substr($this->message, 0, 50) . '...' // Log solo parte del mensaje
                ]);
                DB::table($this->table)->where('id', $this->jobId)->update([
                    'executed_at' => date('Y-m-d H:i:s'),
                    'status' => 'EXECUTED'
                ]);
                $this->fail(new \Exception('El id de cotizacion no existe en la tabla contenedor_consolidado_cotizacion'));
                return;
            }
            $estadoCotizador = $cotizacion->value('estado_cotizador');
            if ($estadoCotizador != 'PENDIENTE') {
                Log::info('El estado del cotizador no es PENDIENTE, no se enviará el mensaje.', [
                    'phoneNumberId' => $this->phoneNumberId,
                    'message' => substr($this->message, 0, 50) . '...' // Log solo parte del mensaje
                ]);
                DB::table($this->table)->where('id', $this->jobId)->update([
                    'executed_at' => date('Y-m-d H:i:s'),
                    'status' => 'EXECUTED'
                ]);
                return;
            }
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post($this->apiUrl, [
                'mensaje' => $this->message,
                'numero' => $this->phoneNumberId
            ]);

            if ($response->failed()) {
                throw new \Exception("Error al enviar mensaje simple: " . $response->body());
            }

            Log::info('Cron Ejecutado Mensaje simple', [
                'phoneNumberId' => $this->phoneNumberId,
                'message' => substr($this->message, 0, 50) . '...' // Log solo parte del mensaje
                
            ]);
            DB::table($this->table)->where('id', $this->jobId)->update([
                'executed_at' => date('Y-m-d H:i:s'),
                'status' => 'EXECUTED'
            ]);
            $idCotizacion = DB::table($this->table)->where('id', $this->jobId)->value('id_cotizacion');
            
            DB::table($this->tableCotizacion)->where('id', $idCotizacion)
            ->where('estado_cotizador', 'PENDIENTE')->
            update([
                'estado_cotizador' => 'CONTACTADO'
            ]);
            
            return $response->json();
            
        } catch (\Exception $e) {
            Log::error('Error en SendSimpleMessageJob: ' . $e->getMessage(), [
                'phoneNumberId' => $this->phoneNumberId
            ]);
            $this->fail($e);
        }
    }
    public function tags()
    {
        return ['send-simple-message-job', 'phoneNumberId:' . $this->phoneNumberId];
    }
}