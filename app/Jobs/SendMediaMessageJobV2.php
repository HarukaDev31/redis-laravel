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

class SendMediaMessageJobV2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $apiUrl;
    
    /** @var string */
    private $fileBase64;
    
    /** @var string|null */
    private $mimeType;
    
    /** @var string|null */
    private $message;
    
    /** @var string */
    private $phoneNumberId;

    private $sleep = 0;

    /** @var string|null */
    private $originalFileName;
    private $fromNumberId;
    private const HTTP_TIMEOUT = 60; // segundos
    private const CONNECT_TIMEOUT = 5; // segundos
    public function __construct(
        string $fileBase64,
        string $phoneNumberId = "51912705923@c.us",
        ?string $mimeType = null,
        ?string $message = null,
        int $sleep = 0,
        ?string $originalFileName = null,
        string $fromNumberId = "consolidado"
    ) {
        $this->fileBase64 = $fileBase64;
        $this->phoneNumberId = $phoneNumberId;
        $this->mimeType = $mimeType;
        $this->message = $message;
        $this->sleep = $sleep;
        $this->originalFileName = $originalFileName;
        $this->fromNumberId = $fromNumberId;
        $this->apiUrl = $this->resolveApiUrl(); // Mover la llamada a env() aquí
    }
    private function resolveApiUrl(): string
    {
        if($this->fromNumberId === "consolidado"){
            return env('WHATSAPP_SERVICE_API_URL').'sendMedia/COORDINATION';
        }
        if($this->fromNumberId === "ventas"){
            return env('WHATSAPP_SERVICE_API_URL').'sendMedia/SELLS';
        }
        if($this->fromNumberId === "curso"){
            return env('WHATSAPP_SERVICE_API_URL').'sendMedia/COURSE';
        }
        if($this->fromNumberId === "administracion"){
            return env('WHATSAPP_SERVICE_API_URL').'sendMedia/ADMINISTRACION';
        }
        return env('WHATSAPP_SERVICE_API_URL').'sendMedia/COORDINATION';
    }
  public function handle()
{
    try {
        $fileName = $this->originalFileName ?: 'file';

        // Determinar MIME type si no está especificado
        if (empty($this->mimeType)) {
            $this->mimeType = $this->detectMimeTypeFromFileName($fileName);
        }

        // Esperar el tiempo especificado
        if ($this->sleep > 0) {
            sleep($this->sleep);
        }

        // Determinar mediatype basado en mimeType
        $mediaType = $this->getMediaType($this->mimeType);

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


        // Preparar payload según el formato especificado - usar base64 directo
        $payload = [
            'number' => $this->phoneNumberId,
            'mediatype' => $mediaType,
            'mimetype' => $this->mimeType,
            'caption' => $this->message ?? '',
            'media' => $this->fileBase64, // Ya viene en base64
            'fileName' => $fileName
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
            throw new \Exception("Error al enviar media: " . $response->getBody()->getContents());
        }

        Log::info('Media enviada', [
            'phoneNumberId' => $this->phoneNumberId,
            'file' => $fileName,
            'type' => $this->mimeType,
            'message' => $this->message,
            'sleep' => $this->sleep,
            'originalFileName' => $this->originalFileName,
            'fromNumberId' => $this->fromNumberId,
            'apiUrl' => $this->apiUrl
        ]);

        return json_decode($response->getBody()->getContents(), true);

    } catch (\Exception $e) {
        Log::error('Error en SendMediaMessageJob: ' . $e->getMessage(), [
            'phoneNumberId' => $this->phoneNumberId,
            'trace' => $e->getTraceAsString()
        ]);
        $this->fail($e);
    } finally {
        // Asegurar que el job se marca como completado
        $this->delete();
    }
}
    private function detectMimeTypeFromFileName(string $fileName): string
    {
        $extension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        switch ($extension) {
            case 'jpg':
            case 'jpeg':
                return 'image/jpeg';
            case 'png':
                return 'image/png';
            case 'mp4':
                return 'video/mp4';
            case 'pdf':
                return 'application/pdf';
            case 'xlsx':
                return 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
            default:
                return 'application/octet-stream';
        }
    }

    private function getMediaType(string $mimeType): string
    {
        if (strpos($mimeType, 'image/') === 0) {
            return 'image';
        }
        
        if (strpos($mimeType, 'video/') === 0) {
            return 'video';
        }
        
        return 'document';
    }
    public function tags()
    {
        return ['send-media-message-job', 'phoneNumberId:' . $this->phoneNumberId];
    }
}