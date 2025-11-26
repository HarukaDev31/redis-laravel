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
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;

class SendMediaInspectionMessageJobV2 implements ShouldQueue
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

    private $inspectionId = null;
    
    /** @var string|null */
    private $originalFileName;
    
    private $table = 'contenedor_consolidado_almacen_inspection';
    private const HTTP_TIMEOUT = 60; // segundos
    private const CONNECT_TIMEOUT = 5; // segundos
    private const MAX_FILE_SIZE = 50 * 1024 * 1024; // 50MB máximo
    public function __construct(
        string $filePath,
        string $phoneNumberId = "51912705923@c.us",
        ?string $mimeType = null,
        ?string $message = null,
        int $sleep = 0,
        int $inspectionId = null,
        ?string $originalFileName = null
    ) {
        $this->filePath = $filePath;
        $this->phoneNumberId = $phoneNumberId;
        $this->mimeType = $mimeType;
        $this->message = $message;
        $this->sleep = $sleep;
        $this->apiUrl = env('WHATSAPP_SERVICE_API_URL').'sendMedia/COORDINATION'; 
        $this->inspectionId = $inspectionId;
        $this->originalFileName = $originalFileName;
    }

    public function handle()
    {
        $tempFile = null;
        
        try {
            if(!$this->inspectionId) {
                Log::error('El id de la inspección no está definido', [
                    'phoneNumberId' => $this->phoneNumberId,
                    'message' => substr($this->message, 0, 50) . '...'
                ]);
                $this->fail(new \Exception('El id de la inspección no está definido'));
                return;
            }

            // Verificar status en la tabla
            $status = DB::table($this->table)->where('id', $this->inspectionId)->value('send_status');

            // Si es una URL, descargar temporalmente
            if (filter_var($this->filePath, FILTER_VALIDATE_URL)) {
                $tempFile = tempnam(sys_get_temp_dir(), 'whatsapp_media_');
                file_put_contents($tempFile, file_get_contents($this->filePath));
                $this->filePath = $tempFile;
                $fileName = $this->originalFileName ?: basename(parse_url($this->filePath, PHP_URL_PATH));
            } else {
                $fileName = $this->originalFileName ?: basename($this->filePath);
            }

            // Validar que el archivo existe
            if (!file_exists($this->filePath)) {
                throw new \Exception("El archivo no existe: " . $this->filePath);
            }

            // Validar tamaño del archivo
            $fileSize = filesize($this->filePath);
            if ($fileSize === false) {
                throw new \Exception("No se pudo obtener el tamaño del archivo: " . $this->filePath);
            }

            if ($fileSize > self::MAX_FILE_SIZE) {
                throw new \Exception("El archivo es demasiado grande: " . round($fileSize / 1024 / 1024, 2) . "MB (máximo: " . round(self::MAX_FILE_SIZE / 1024 / 1024, 2) . "MB)");
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

            // Leer el contenido del archivo
            $fileContents = file_get_contents($this->filePath);
            if ($fileContents === false) {
                throw new \Exception("No se pudo leer el archivo: " . $this->filePath);
            }

            // Preparar payload según el formato V2 - siempre usar base64
            $mediaBase64 = base64_encode($fileContents);
            
            // Validar que la codificación base64 fue exitosa
            if ($mediaBase64 === false || empty($mediaBase64)) {
                throw new \Exception("Error al codificar el archivo en base64");
            }

            $payload = [
                'number' => $this->phoneNumberId,
                'mediatype' => $mediaType,
                'mimetype' => $this->mimeType,
                'caption' => $this->message ?? '',
                'media' => $mediaBase64,
                'fileName' => $fileName
            ];

            // Log antes de enviar (sin el contenido del media para no saturar los logs)
            Log::info('Enviando media inspection V2', [
                'phoneNumberId' => $this->phoneNumberId,
                'fileName' => $fileName,
                'fileSize' => $fileSize,
                'mimeType' => $this->mimeType,
                'mediaType' => $mediaType,
                'inspectionId' => $this->inspectionId,
                'apiUrl' => $this->apiUrl,
                'payloadSize' => strlen(json_encode($payload)) . ' bytes'
            ]);

            $response = $client->post($this->apiUrl, [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                    'apikey' => env('API_KEY_V2'),
                ]
            ]);

            // Leer el cuerpo de la respuesta una sola vez
            $responseBody = $response->getBody()->getContents();

            if ($response->getStatusCode() >= 400) {
                // Log el error completo antes de lanzar la excepción
                Log::error('Error del API al enviar media inspection V2', [
                    'statusCode' => $response->getStatusCode(),
                    'responseBody' => $responseBody,
                    'phoneNumberId' => $this->phoneNumberId,
                    'inspectionId' => $this->inspectionId,
                    'fileName' => $fileName,
                    'apiUrl' => $this->apiUrl
                ]);
                
                // Intentar parsear la respuesta para obtener más detalles
                $errorData = json_decode($responseBody, true);
                $errorMessage = is_array($errorData) && isset($errorData['response']['message']) 
                    ? $errorData['response']['message'] 
                    : $responseBody;
                
                throw new \Exception("Error al enviar media inspection (HTTP {$response->getStatusCode()}): " . $errorMessage);
            }

            Log::info('Response V2: ' . $responseBody);
            Log::info('Media inspection V2 enviada', [
                'phoneNumberId' => $this->phoneNumberId,
                'file' => $fileName,
                'type' => $this->mimeType,
                'inspectionId' => $this->inspectionId
            ]);

            // Actualizar status a SENDED
            DB::table($this->table)->where('id', $this->inspectionId)->update([
                'send_status' => 'SENDED'
            ]);

            return json_decode($responseBody, true);

        } catch (\Exception $e) {
            Log::error('Error en SendMediaInspectionMessageJobV2: ' . $e->getMessage(), [
                'phoneNumberId' => $this->phoneNumberId,
                'inspectionId' => $this->inspectionId,
                'trace' => $e->getTraceAsString()
            ]);
            $this->fail($e);
        } finally {
            // Eliminar archivo temporal si existe
            if ($tempFile && file_exists($tempFile)) {
                unlink($tempFile);
            }
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
        return ['send-media-inspection-message-job-v2', 'phoneNumberId:' . $this->phoneNumberId, 'inspectionId:' . $this->inspectionId];
    }
}
