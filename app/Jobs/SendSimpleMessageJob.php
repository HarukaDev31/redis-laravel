<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendSimpleMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $apiUrl = 'https://whatsapp.probusiness.pe/enviar-mensaje';
    
    /** @var string */
    private $message;
    
    /** @var string */
    private $phoneNumberId;

    private $sleep;

    public function __construct(string $message, string $phoneNumberId = "51912705923@c.us", int $sleep = 0)
    {
        $this->message = $message;
        $this->phoneNumberId = $phoneNumberId;
        $this->sleep = $sleep;
    }

    public function handle()
    {
        try {
           
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post($this->apiUrl, [
                'mensaje' => $this->message,
                'numero' => $this->phoneNumberId
            ]);

            if ($response->failed()) {
                throw new \Exception("Error al enviar mensaje simple: " . $response->body());
            }

            Log::info('Mensaje simple enviado', [
                'phoneNumberId' => $this->phoneNumberId,
                'message' => substr($this->message, 0, 50) . '...' // Log solo parte del mensaje
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