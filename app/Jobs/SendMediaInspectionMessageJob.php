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
class SendMediaInspectionMessageJob implements ShouldQueue
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
        $this->apiUrl = env('COORDINATION_API_URL'); 
        $this->inspectionId = $inspectionId;
        $this->originalFileName = $originalFileName;
    }

    public function handle()
    {
        try {
            if(!$this->inspectionId) {
                Log::error('El id de la inspección no está definido', [
                    'phoneNumberId' => $this->phoneNumberId,
                    'message' => substr($this->message, 0, 50) . '...' // Log solo parte del mensaje
                ]);
                $this->fail(new \Exception('El id de la inspección no está definido'));
                return;
            }
            //find  id in table contenedor_consolidado_almacen_inspection
            $status = DB::table($this->table)->where('id', $this->inspectionId)->value('send_status');
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
            Log::info('Response: ' . $response->body());
            Log::info('Media enviada', [
                'phoneNumberId' => $this->phoneNumberId,
                'file' => $fileName,
                'type' => $this->mimeType
            ]);
            //update status to SENDED
            DB::table($this->table)->where('id', $this->inspectionId)->update([
                'send_status' => 'SENDED'
            ]);
            

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
    public function tags()
    {
        return ['send-media-message-job', 'phoneNumberId:' . $this->phoneNumberId];
    }
}