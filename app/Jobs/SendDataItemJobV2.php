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

class SendDataItemJobV2 implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $apiUrl;
    
    /** @var string */
    private $message;
    
    /** @var string */
    private $filePath;
    
    /** @var string */
    private $phoneNumberId;
    
    private $sleep;
    private const HTTP_TIMEOUT = 60; // segundos
    private const CONNECT_TIMEOUT = 5; // segundos
    public function __construct(string $message, string $filePath, string $phoneNumberId = "51912705923@c.us", int $sleep = 0)
    {
        $this->message = $message;
        $this->filePath = $filePath;
        $this->phoneNumberId = $phoneNumberId;
        $this->sleep = $sleep;
        $this->apiUrl = env('WHATSAPP_SERVICE_API_URL').'sendMedia/COORDINATION';
    }

    public function handle()
    {
        $tempFile = null;
        
        try {
            // Esperar el tiempo especificado
            if ($this->sleep > 0) {
                sleep($this->sleep);
            }

            // Si es una URL, descargar temporalmente
            if (filter_var($this->filePath, FILTER_VALIDATE_URL)) {
                $tempFile = tempnam(sys_get_temp_dir(), 'whatsapp_data_');
                file_put_contents($tempFile, file_get_contents($this->filePath));
                $this->filePath = $tempFile;
                $fileName = basename(parse_url($this->filePath, PHP_URL_PATH));
            } else {
                $fileName = basename($this->filePath);
            }

            // Determinar MIME type
            $mimeType = $this->detectMimeType($this->filePath);
            $mediaType = $this->getMediaType($mimeType);
            Log::info('mimeType: ' . $mimeType);
            Log::info('mediaType: ' . $mediaType);
            Log::info('apiUrl: ' . $this->apiUrl);
            Log::info('phoneNumberId: ' . $this->phoneNumberId);
            Log::info('message: ' . $this->message);
            Log::info('filePath: ' . $this->filePath);
            Log::info('sleep: ' . $this->sleep);
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

            // Preparar payload según el formato V2 - siempre usar base64
            $payload = [
                'number' => $this->phoneNumberId,
                'mediatype' => $mediaType,
                'mimetype' => $mimeType,
                'caption' => $this->message,
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
                throw new \Exception("Error al enviar datos con archivo: " . $response->getBody()->getContents());
            }

            Log::info('Datos con archivo V2 enviados', [
                'phoneNumberId' => $this->phoneNumberId,
                'file' => $fileName,
                'type' => $mimeType
            ]);

            return json_decode($response->getBody()->getContents(), true);

        } catch (\Exception $e) {
            Log::error('Error en SendDataItemJobV2: ' . $e->getMessage(), [
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
        return ['send-data-item-job-v2', 'phoneNumberId:' . $this->phoneNumberId];
    }
}
