<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendMediaMessageJob implements ShouldQueue
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
            return env('COORDINATION_API_URL');
        }
        if($this->fromNumberId === "ventas"){
            return env('SELLS_API_URL');
        }
        if($this->fromNumberId === "curso"){
            return env('CURSO_API_URL');
        }
        return env('COORDINATION_API_URL');
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

        $response = Http::timeout(60) // Aumentar timeout a 1 minuto
            ->asMultipart()
            ->post($this->apiUrl, [
                [
                    'name' => 'numero',
                    'contents' => $this->phoneNumberId
                ],
                [
                    'name' => 'mensaje',
                    'contents' => $this->message ?? ''
                ],
                [
                    'name' => 'archivo',
                    'contents' => fopen($this->filePath, 'r'),
                    'filename' => $fileName,
                    'headers' => [
                        'Content-Type' => $this->mimeType
                    ]
                ]
            ]);

        if ($response->failed()) {
            throw new \Exception("Error al enviar media: " . $response->body());
        }

        Log::info('Media enviada', [
            'phoneNumberId' => $this->phoneNumberId,
            'file' => $fileName,
            'type' => $this->mimeType
        ]);

        return $response->json();

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
    public function tags()
    {
        return ['send-media-message-job', 'phoneNumberId:' . $this->phoneNumberId];
    }
}