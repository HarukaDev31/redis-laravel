<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class SendWelcomeMessageJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $apiUrl; 
    
    /** @var string */
    private $carga;
    
    /** @var string */
    private $phoneNumberId;

    private $sleep;

    public function __construct(string $carga, string $phoneNumberId = "51912705923@c.us", int $sleep = 0)
    {
        $this->carga = $carga;
        $this->phoneNumberId = $phoneNumberId;
        $this->sleep = $sleep;
        $this->apiUrl = env('COORDINATION_API_URL'); // Mover la llamada a env() aquí
    }

    public function handle()
    {
        try {
            sleep($this->sleep);
            $response = Http::withHeaders([
                'Content-Type' => 'application/json'
            ])->post($this->apiUrl, [
                'mensaje' => $this->buildMessage(),
                'numero' => $this->phoneNumberId
            ]);

            if ($response->failed()) {
                throw new \Exception("Error al enviar mensaje de bienvenida: " . $response->body());
            }

            Log::info('Mensaje de bienvenida enviado', [
                'carga' => $this->carga,
                'phoneNumberId' => $this->phoneNumberId
            ]);
            
            return $response->json();
            
        } catch (\Exception $e) {
            Log::error('Error en SendWelcomeMessageJob: ' . $e->getMessage(), [
                'phoneNumberId' => $this->phoneNumberId
            ]);
            $this->fail($e);
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
}