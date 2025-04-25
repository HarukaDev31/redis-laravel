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

    private $apiUrl = env('COORDINATION_API_URL') ;
    
    /** @var string */
    private $filePath;
    
    /** @var string|null */
    private $mimeType;
    
    /** @var string|null */
    private $message;
    
    /** @var string */
    private $phoneNumberId;

    private $sleep = 0;

    public function __construct(
        string $filePath,
        string $phoneNumberId = "51912705923@c.us",
        ?string $mimeType = null,
        ?string $message = null,
        int $sleep = 0
    ) {
        $this->filePath = $filePath;
        $this->phoneNumberId = $phoneNumberId;
        $this->mimeType = $mimeType;
        $this->message = $message;
        $this->sleep = $sleep;
    }

    public function handle()
    {
        try {
            // Si es una URL, descargar temporalmente
            if (filter_var($this->filePath, FILTER_VALIDATE_URL)) {
                $tempFile = tempnam(sys_get_temp_dir(), 'whatsapp_media_');
                file_put_contents($tempFile, file_get_contents($this->filePath));
                $this->filePath = $tempFile;
                $fileName = basename(parse_url($this->filePath, PHP_URL_PATH));
            } else {
                $fileName = basename($this->filePath);
            }

            // Determinar MIME type si no está especificado
            if (empty($this->mimeType)) {
                $this->mimeType = $this->detectMimeType($this->filePath);
            }
            //wait 3 seconds
            sleep($this->sleep); // Esperar el tiempo especificado antes de enviar el mensaje
            $response = Http::asMultipart()
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

            // Eliminar archivo temporal si se creó
            

            return $response->json();

        } catch (\Exception $e) {
            Log::error('Error en SendMediaMessageJob: ' . $e->getMessage(), [
                'phoneNumberId' => $this->phoneNumberId
            ]);
            $this->fail($e);
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
}