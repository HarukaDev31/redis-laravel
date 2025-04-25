<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendDataItemJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $apiUrl ;
    
    /** @var string */
    private $message;
    
    /** @var string */
    private $filePath;
    
    /** @var string */
    private $phoneNumberId;
    
    private $sleep;
    public function __construct(string $message, string $filePath, string $phoneNumberId = "51912705923@c.us", int $sleep = 0)
    {
        $this->message = $message;
        $this->filePath = $filePath;
        $this->phoneNumberId = $phoneNumberId;
        $this->sleep = $sleep;
        $this->apiUrl = env('COORDINATION_API_URL'); // Move env() call here

    }

    public function handle()
    {
        try {
            sleep($this->sleep); // Esperar el tiempo especificado antes de enviar el mensaje
            $response = Http::asMultipart()
                ->post($this->apiUrl, [
                    [
                        'name' => 'numero',
                        'contents' => $this->phoneNumberId
                    ],
                    [
                        'name' => 'mensaje',
                        'contents' => $this->message
                    ],
                    [
                        'name' => 'archivo',
                        'contents' => fopen($this->filePath, 'r'),
                        'filename' => basename($this->filePath),
                        'headers' => [
                            'Content-Type' => 'application/pdf'
                        ]
                    ]
                ]);

            if ($response->failed()) {
                throw new \Exception("Error al enviar datos con archivo: " . $response->body());
            }

            Log::info('Datos con archivo enviados', [
                'phoneNumberId' => $this->phoneNumberId,
                'file' => $this->filePath
            ]);

            // Eliminar archivo temporal después de enviarlo
           
            return $response->json();

        } catch (\Exception $e) {
            Log::error('Error en SendDataItemJob: ' . $e->getMessage(), [
                'phoneNumberId' => $this->phoneNumberId
            ]);
            $this->fail($e);
        }
    }
}