<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;

class SendWelcomeMessageJobV2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $apiUrl; 
    
    /** @var string */
    private $carga;
    
    /** @var string */
    private $phoneNumberId;

    private $sleep;
    private const HTTP_TIMEOUT = 60; // segundos
    private const CONNECT_TIMEOUT = 5; // segundos
    public function __construct(string $carga, string $phoneNumberId = "51912705923@c.us", int $sleep = 0)
    {
        $this->carga = $carga;
        $this->phoneNumberId = $phoneNumberId;
        $this->sleep = $sleep;
        $this->apiUrl = env('WHATSAPP_SERVICE_API_URL').'sendText/COORDINATION';
    }

    public function handle()
    {
        try {
            // Esperar el tiempo especificado
            if ($this->sleep > 0) {
                sleep($this->sleep);
            }

            // Configurar cliente HTTP con timeout real
            $stack = HandlerStack::create(new CurlHandler());
            
            // Crear cliente Guzzle
            $client = new \GuzzleHttp\Client([
                'handler' => $stack,
                'timeout' => self::HTTP_TIMEOUT,
                'connect_timeout' => self::CONNECT_TIMEOUT,
                'http_errors' => false,
                'verify' => false // Solo si no usas SSL
            ]);

            // Preparar payload según el formato V2
            $payload = [
                'number' => $this->phoneNumberId,
                'text' => $this->buildMessage()
            ];

            $response = $client->post($this->apiUrl, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'apikey' => env('API_KEY_V2'),
                ]
            ]);

            if ($response->getStatusCode() >= 400) {
                throw new \Exception("Error al enviar mensaje de bienvenida: " . $response->getBody()->getContents());
            }

            Log::info('Mensaje de bienvenida V2 enviado', [
                'carga' => $this->carga,
                'phoneNumberId' => $this->phoneNumberId
            ]);
            
            return json_decode($response->getBody()->getContents(), true);
            
        } catch (\Exception $e) {
            Log::error('Error en SendWelcomeMessageJobV2: ' . $e->getMessage(), [
                'phoneNumberId' => $this->phoneNumberId,
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail($e);
        } finally {
            // Asegurar que el job se marca como completado
            $this->delete();
        }
    }

    private function buildMessage(): string
    {
        return '
Hola 🙋🏻‍♀, te escribe el área de coordinación de probusiness, 
yo me encargaré de ayudarte en tu importación del *consolidado #' . $this->carga . '*.

📢 Preste atención al siguiente paso: 
*Rotulado* 👇🏼
Tienes que indicarle a tu proveedor que las cajas máster 📦 cuenten con un rotulado para identificar tus paquetes y diferenciarlas de los demás cuando llegue a nuestro almacén.

☑ El documento está en idioma chino, solo debes enviarle a tu proveedor 📤

Nota: No cambiar ninguno de los datos, en caso tu proveedor tenga alguna consulta, se puede comunicarse:

🙍🏻‍♂ Álmacen China: Mr. Younus 
📞 Wechat: 13185122926';
    }

    public function tags()
    {
        return ['send-welcome-message-job-v2', 'phoneNumberId:' . $this->phoneNumberId];
    }
}
