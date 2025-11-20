<?php
namespace App\Jobs;

use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use GuzzleHttp\Client;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Handler\CurlHandler;

/**
 * Summary of SendConstanciaCurso
 * Esta clase se encarga de generar y enviar una constancia de curso por WhatsApp.
 * Utiliza un job de Laravel para manejar la generación del PDF y el envío del mensaje.
 * La constancia incluye el nombre del participante y la fecha de emisión.
 * El PDF se genera a partir de una vista Blade y se envía como un archivo adjunto en un mensaje de WhatsApp.
 * La clase maneja errores y actualiza el estado del pedido de curso en la base de datos.
 * @package App\Jobs
 * @author Tu Nombre
 * @version 1.0
 * @since 2023-10-01
 * @param string $phoneNumberId El ID del número de teléfono de WhatsApp al que se enviará la constancia.
 * @param mixed $pedidoCurso El objeto del pedido de curso que contiene la información del participante.
 */
class SendConstanciaCurso implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const HTTP_TIMEOUT = 60; // segundos
    private const CONNECT_TIMEOUT = 5; // segundos

    private $apiUrl;
    private $phoneNumberId;
    private $pedidoCurso;
    private $table = 'pedido_curso';

    public function __construct(
        string $phoneNumberId = "51912705923@c.us",
        $pedidoCurso = null
    ) {
        //first trim number and remove +
        $phoneNumberId = trim($phoneNumberId);
        $phoneNumberId = str_replace('+', '', $phoneNumberId);
        //later if number has low 9 digits, add 51
        if (strlen($phoneNumberId) <= 9) {
            $phoneNumberId = '51' . $phoneNumberId;
        }
        //check if number has @c.us and remove it
        if (str_ends_with($phoneNumberId, '@c.us')) {
            $phoneNumberId = str_replace('@c.us', '', $phoneNumberId);
        }
        
        $this->phoneNumberId = $phoneNumberId;
        $this->pedidoCurso   = $pedidoCurso;
        $this->apiUrl = env('WHATSAPP_SERVICE_API_URL').'sendMedia/COURSE';
    }

    public function handle()
    {
        $pdfPath = null;

        try {
            //check if img/fondo.png exists 

            if (! $this->pedidoCurso) {
                Log::error('El pedido de curso no está definido', [
                    'phoneNumberId' => $this->phoneNumberId,
                ]);
                $this->fail(new \Exception('El pedido de curso no está definido'));
                return;
            }

            $nombreParticipante = $this->pedidoCurso->No_Entidad ?? 'Participante';
            $fechaEmision       = $this->pedidoCurso->Fe_Fin ?? now()->format('Y-m-d');
            $emailParticipante  = $this->pedidoCurso->Txt_Email_Entidad ?? 'harukakasugano31@gmail.com';
            // Generar PDF desde el Blade
            $pdfPath = $this->generatePDF($nombreParticipante, $fechaEmision);
            
            // Validar que el PDF se generó correctamente
            if (!file_exists($pdfPath)) {
                throw new \Exception('El archivo PDF no se generó correctamente');
            }
            
            $fileSize = filesize($pdfPath);
            if ($fileSize < 1024) { // Menos de 1KB
                throw new \Exception('PDF generado es demasiado pequeño (' . $fileSize . ' bytes), posible error en la generación');
            }
            
            Log::info('PDF generado en la ruta: ' . $pdfPath, [
                'phoneNumberId' => $this->phoneNumberId,
                'pedidoCurso'   => json_encode($this->pedidoCurso),
                'fileSize' => $fileSize . ' bytes'
            ]);
            
            // Enviar el PDF por WhatsApp
            $response = $this->sendPDFToWhatsApp($pdfPath);

            if ($response->getStatusCode() < 400) {
                // WhatsApp se envió exitosamente
                Log::info('Constancia de WhatsApp enviada exitosamente', [
                    'phoneNumberId' => $this->phoneNumberId,
                    'pedidoCurso'   => json_encode($this->pedidoCurso),
                ]);

                // Actualizar estado a ENVIADO
                DB::table($this->table)
                    ->where('ID_Pedido_Curso', $this->pedidoCurso->ID_Pedido_Curso)
                    ->update([
                        'send_constancia' => 'SENDED',
                    ]);

                // Enviar correo SOLO después de que WhatsApp se envíe exitosamente
                $this->sendEmailWithErrorHandling($pdfPath, $emailParticipante);

                return json_decode($response->getBody()->getContents(), true);
            } else {
                Log::error('Error al enviar constancia por WhatsApp: ' . $response->getBody()->getContents(), [
                    'phoneNumberId' => $this->phoneNumberId,
                    'pedidoCurso'   => json_encode($this->pedidoCurso),
                ]);

                // Marcar como enviado aunque falle WhatsApp
                DB::table($this->table)
                    ->where('ID_Pedido_Curso', $this->pedidoCurso->ID_Pedido_Curso)
                    ->update([
                        'send_constancia' => 'SENDED',
                    ]);

                return;
            }
        } catch (\Exception $e) {
            Log::error('Error en SendConstanciaCurso: ' . $e->getMessage(), [
                'phoneNumberId' => $this->phoneNumberId,
                'pedidoCurso'   => json_encode($this->pedidoCurso),
                'error'         => $e->getTraceAsString(),
            ]);

            $this->fail($e);
        } finally {
            // Guardar el PDF temporal si se generó
            if ($pdfPath && file_exists($pdfPath)) {
                try {
                    $fileName = basename($pdfPath);
                    DB::table($this->table)
                        ->where('ID_Pedido_Curso', $this->pedidoCurso->ID_Pedido_Curso)
                        ->update([
                            'url_constancia' => 'storage/public/' . $fileName,
                        ]);
                    Log::info('PDF guardado: ' . $pdfPath);
                } catch (\Exception $e) {
                    Log::error('Error al guardar el PDF: ' . $e->getMessage(), [
                        'pdfPath' => $pdfPath,
                    ]);
                }
            }
        }
    }

    private function generatePDF(string $nombre, string $fecha): string
    {
        try {
            // Verificar que existe la imagen de fondo
            $fondoPath = public_path('img/fondo.png');
            if (!file_exists($fondoPath)) {
                throw new \Exception('No se encontró la imagen de fondo en: ' . $fondoPath);
            }

            //encode base img
            $fondoImg = base64_encode(file_get_contents($fondoPath));
            
            //convert fecha d/m/y to day de month de year
            $fecha = date('d \d\e F \d\e Y', strtotime($fecha));
            
            //reemplazar mes en español
            $meses = [
                'January'   => 'Enero',
                'February'  => 'Febrero',
                'March'     => 'Marzo',
                'April'     => 'Abril',
                'May'       => 'Mayo',
                'June'      => 'Junio',
                'July'      => 'Julio',
                'August'    => 'Agosto',
                'September' => 'Septiembre',
                'October'   => 'Octubre',
                'November'  => 'Noviembre',
                'December'  => 'Diciembre',
            ];
            $fecha = str_replace(array_keys($meses), array_values($meses), $fecha);

            // Configurar opciones de DomPDF como array
            $options = [
                'fontDir' => storage_path('fonts/'),
                'fontCache' => storage_path('fonts/'),
                'isHtml5ParserEnabled' => true,
                'isRemoteEnabled' => false,
                'dpi' => 150,
            ];

            // Crear el PDF con las opciones configuradas
            $pdf = PDF::setOptions($options)->loadView('constancia', [
                'nombre'   => $nombre,
                'fecha'    => $fecha,
                'fondoImg' => $fondoImg,
            ]);

            // Configurar el PDF tamaño carta horizontal
            $pdf->setPaper('letter', 'landscape');

            $fileName = 'constancia_' . Str::slug($nombre) . '_' . time() . '.pdf';
            $pdfPath = storage_path('app/public/' . $fileName);
            
            // Asegurar que el directorio existe
            if (!file_exists(dirname($pdfPath))) {
                mkdir(dirname($pdfPath), 0755, true);
            }

            // Generar y guardar el PDF
            $pdfContent = $pdf->output();
            file_put_contents($pdfPath, $pdfContent);

            // Verificar que el PDF se generó correctamente
            $fileSize = filesize($pdfPath);
            if ($fileSize < 1024) { // Menos de 1KB indica un problema
                throw new \Exception('PDF generado es demasiado pequeño (' . $fileSize . ' bytes), posible error en la generación');
            }

            Log::info('PDF generado exitosamente', [
                'path'   => $pdfPath,
                'nombre' => $nombre,
                'size'   => $fileSize . ' bytes',
            ]);

            return $pdfPath;
        } catch (\Exception $e) {
            Log::error('Error generando PDF: ' . $e->getMessage(), [
                'nombre' => $nombre,
                'fecha' => $fecha,
                'trace' => $e->getTraceAsString()
            ]);
            throw new \Exception('Error al generar la constancia PDF: ' . $e->getMessage());
        }
    }

    private function sendPDFToWhatsApp(string $pdfPath)
    {
        $fileName = basename($pdfPath);
        $mensaje  = "🎓 ¡Felicitaciones! Aquí tienes tu constancia del Taller Virtual de Importación.\n\n" .
            "Equivalente a 12 horas académicas.\n" .
            "Dictado por nuestros expertos en comercio internacional.\n\n" .
            "¡Gracias por tu participación! 🎉";

        // Configurar cliente HTTP con timeout real
        $stack = HandlerStack::create(new CurlHandler());
        
        // Crear cliente Guzzle
        $client = new Client([
            'handler' => $stack,
            'timeout' => self::HTTP_TIMEOUT,
            'connect_timeout' => self::CONNECT_TIMEOUT,
            'http_errors' => false,
            'verify' => false
        ]);

        // Preparar payload según el formato V2 - siempre usar base64
        $payload = [
            'number' => $this->phoneNumberId,
            'mediatype' => 'document',
            'mimetype' => 'application/pdf',
            'caption' => $mensaje,
            'media' => base64_encode(file_get_contents($pdfPath)),
            'fileName' => $fileName
        ];

        return $client->post($this->apiUrl, [
            'json' => $payload,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept' => 'application/json',
                'apikey' => env('API_KEY_V2'),
            ]
        ]);
    }

    private function sendEmailWithErrorHandling(string $pdfPath, string $emailParticipante)
    {
        try {
            DB::table($this->table)
                ->where('ID_Pedido_Curso', $this->pedidoCurso->ID_Pedido_Curso)
                ->update([
                    'send_constancia' => 'SENDED',
                ]);
            Mail::raw('🎓 ¡Felicitaciones! Aquí tienes tu constancia del Taller Virtual de Importación.

                    Equivalente a 12 horas académicas.
                    Dictado por nuestros expertos en comercio internacional.

                    ¡Gracias por tu participación! ', function ($message) use ($pdfPath, $emailParticipante) {
                $message->from('noreply@lae.one', 'Probusiness')
                    ->to($emailParticipante)
                    ->subject('Constancia de Curso-Probusiness')
                    ->attach($pdfPath, [
                        'as'   => basename($pdfPath),
                        'mime' => 'application/pdf',
                    ]);
            });
            Log::info('Correo enviado exitosamente', ['email' => $emailParticipante]);
        } catch (\Exception $e) {
            Log::error('Error al enviar correo: ' . $e->getMessage(), [
                'email' => $emailParticipante,
                'error' => $e->getTraceAsString(),
            ]);
            // NO lanzar excepción - el job debe continuar como exitoso
        }
    }

    public function tags()
    {
        return [
            'send-constancia-curso-job',
            'phoneNumberId:' . $this->phoneNumberId,
            'pedidoCurso:' . json_encode($this->pedidoCurso->No_Entidad), // Asegúrate de que esto no sea demasiado grande
        ];
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Job SendConstanciaCurso falló completamente', [
            'phoneNumberId' => $this->phoneNumberId,
            'pedidoCurso'   => $this->pedidoCurso,
            'error'         => $exception->getMessage(),
        ]);
    }
}
