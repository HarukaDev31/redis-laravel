<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;

class SendSimpleMessageJobV2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    // Constantes para timeouts
    private const HTTP_TIMEOUT = 60; // segundos
    private const CONNECT_TIMEOUT = 5; // segundos

    private $apiUrl;
    private $message;
    private $phoneNumberId;
    private $sleep;
    private $fromNumberId;

    public function __construct(
        string $message,
        string $phoneNumberId = "51912705923@c.us",
        int $sleep = 0,
        string $fromNumberId = "consolidado"
    ) {
        $this->message = $message;
        $this->phoneNumberId = $phoneNumberId;
        $this->sleep = $sleep;
        $this->fromNumberId = $fromNumberId;
        $this->apiUrl = $this->resolveApiUrl();
    }

    private function resolveApiUrl(): string
    {
        if($this->fromNumberId === "consolidado"){
            return env('COORDINATION_API_URL_V2');
        }
        if($this->fromNumberId === "ventas"){
            return env('SELLS_API_URL_V2');
        }
        if($this->fromNumberId === "curso"){
            return env('CURSO_API_URL_V2');
        }
        return env('COORDINATION_API_URL_V2');
    }

    public function handle()
    {
        try {
            // Espera inicial controlada
            if ($this->sleep > 0) {
                sleep(min($this->sleep, 60)); // Máximo 60 segundos de sleep
            }
            //from phone replace spaces and + just keep numbers
            $fromPhone = preg_replace('/[^0-9]/', '', $this->phoneNumberId);
            $this->phoneNumberId = $fromPhone;
            // Configurar cliente HTTP con timeout real
            $handler = new CurlHandler();
            $stack = HandlerStack::create($handler);
            
            $client = new \GuzzleHttp\Client([
                'handler' => $stack,
                'timeout' => self::HTTP_TIMEOUT,
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'http_errors' => false,
                'verify' => false // Solo si no usas SSL
            ]);

            // log author
            Log::info('Authorization: ' . env('API_KEY_V2'));
            $response = $client->post($this->apiUrl, [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'Authorization' => 'Bearer ' . env('API_KEY_V2')
                ],
                'json' => [
                    'text' => $this->message,
                    'numero' => $this->phoneNumberId
                ]
            ]);

            // Verificar respuesta
            if ($response->getStatusCode() != 200) {
                throw new \Exception("Error al enviar mensaje simple: " . $response->getBody());
            }

            Log::info('Mensaje simple enviado', [
                'phoneNumberId' => $this->phoneNumberId,
                'message' => substr($this->message, 0, 100) . (strlen($this->message) > 100 ? '...' : ''),
                'statusCode' => $response->getStatusCode(),
                'apiUrl' => $this->apiUrl
            ]);
            $this->delete(); // Esto es clave para indicar finalización exitosa

            return json_decode($response->getBody(), true);

        } catch (\Throwable $e) {
            Log::error('Error en SendSimpleMessageJob: ' . $e->getMessage(), [
                'phoneNumberId' => $this->phoneNumberId,
                'apiUrl' => $this->apiUrl,
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail($e);
        }
    }

    public function tags()
    {
        return [
            'send-simple-message-job', 
            'phoneNumberId:' . $this->phoneNumberId,
            'fromNumber:' . $this->fromNumberId
        ];
    }
}