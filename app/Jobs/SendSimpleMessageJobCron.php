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

    private $apiUrl = env('SELLS_API_URL') ;
    private $table = 'contenedor_consolidado_cotizacion_crons';
    
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
    }

    public function handle()
    {
        try {
            // Esperar el tiempo especificado antes de enviar el mensaje
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
            //update $this->table set executed_at = now() where id = $this->jobId status 'EXECUTED'
            DB::table($this->table)->where('id', $this->jobId)->update([
                'executed_at' => date('Y-m-d H:i:s'),
                'status' => 'EXECUTED'
            ]);
            return $response->json();
            
        } catch (\Exception $e) {
            Log::error('Error en SendSimpleMessageJob: ' . $e->getMessage(), [
                'phoneNumberId' => $this->phoneNumberId
            ]);
            $this->fail($e);
        }
    }
}