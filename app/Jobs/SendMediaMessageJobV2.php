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
    private $filePath;
    
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
        string $filePath,
        string $phoneNumberId = "51912705923@c.us",
        ?string $mimeType = null,
        ?string $message = null,
        int $sleep = 0,
        ?string $originalFileName = null,
        string $fromNumberId = "consolidado"
    ) {
        $this->filePath = $filePath;
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
    $tempFile = null;
    
    try {
        // Si es una URL, descargar temporalmente
        if (filter_var($this->filePath, FILTER_VALIDATE_URL)) {
            $tempFile = tempnam(sys_get_temp_dir(), 'whatsapp_media_');
            file_put_contents($tempFile, file_get_contents($this->filePath));
            $this->filePath = $tempFile;
            $fileName = $this->originalFileName ?: basename(parse_url($this->filePath, PHP_URL_PATH));
        } else {
            $fileName = $this->originalFileName ?: basename($this->filePath);
        }

        // Determinar MIME type si no está especificado
        if (empty($this->mimeType)) {
            $this->mimeType = $this->detectMimeType($this->filePath);
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


        // Preparar payload según el formato especificado - siempre usar base64
        $payload = [
            'number' => $this->phoneNumberId,
            'text' => $this->message ?? '',
            'mediatype' => $mediaType,
            'mimetype' => $this->mimeType,
            'caption' => $this->message ?? '',
            'media' => base64_encode(file_get_contents($this->filePath)),
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
            'type' => $this->mimeType
        ]);

        return json_decode($response->getBody()->getContents(), true);

    } catch (\Exception $e) {
        Log::error('Error en SendMediaMessageJob: ' . $e->getMessage(), [
            'phoneNumberId' => $this->phoneNumberId,
            'trace' => $e->getTraceAsString()
        ]);
        $this->fail($e);
    } finally {
        // Eliminar archivo temporal si existe
        if ($tempFile && file_exists($tempFile)) {
            unlink($tempFile);
        }
        
        // Asegurar que el job se marca como completado
        $this->delete();
    }
}
    private function detectMimeType(string $filePath): string
    {
        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        
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
                return mime_content_type($filePath) ?: 'application/octet-stream';
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