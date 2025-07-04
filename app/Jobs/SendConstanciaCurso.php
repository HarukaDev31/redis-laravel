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
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use Barryvdh\DomPDF\Facade\Pdf;
use Dompdf\Options;
use Dompdf\Dompdf;

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

    private $apiUrl;
    private $phoneNumberId;
    private $pedidoCurso;
    private $table = 'pedido_curso';

    public function __construct(
        string $phoneNumberId = "51912705923@c.us",
        $pedidoCurso = null
    ) {
        //check if number has @c.us
        if (!str_ends_with($phoneNumberId, '@c.us')) {
            $phoneNumberId .= '@c.us'; // Asegurar que el número tenga el formato correcto
        }
        $this->phoneNumberId = $phoneNumberId;
        $this->pedidoCurso = $pedidoCurso;
        $this->apiUrl = env('SELLS_API_URL');
    }

    public function handle()
    {
        $pdfPath = null;

        try {
            //check if img/fondo.png exists 

            if (!$this->pedidoCurso) {
                Log::error('El pedido de curso no está definido', [
                    'phoneNumberId' => $this->phoneNumberId
                ]);
                $this->fail(new \Exception('El pedido de curso no está definido'));
                return;
            }

            $nombreParticipante = $this->pedidoCurso->No_Entidad ?? 'Participante';
            $fechaEmision = $this->pedidoCurso->Fe_Fin ?? now()->format('Y-m-d');
            $emailParticipante = $this->pedidoCurso->Txt_Email_Entidad ?? 'harukakasugano31@gmail.com';
            // Generar PDF desde el Blade
            $pdfPath = $this->generatePDF($nombreParticipante, $fechaEmision);

            // Enviar el PDF por WhatsApp
            $response = $this->sendPDFToWhatsApp($pdfPath);
                
            if ($response->successful()) {
                // WhatsApp se envió exitosamente
                Log::info('Constancia de WhatsApp enviada exitosamente', [
                    'phoneNumberId' => $this->phoneNumberId,
                    'pedidoCurso' => json_encode($this->pedidoCurso),
                ]);
                
                // Actualizar estado a ENVIADO
                DB::table($this->table)
                    ->where('ID_Pedido_Curso', $this->pedidoCurso->ID_Pedido_Curso)
                    ->update([
                        'send_constancia' => 'SENDED',
                    ]);
                
                // Enviar correo SOLO después de que WhatsApp se envíe exitosamente
                $this->sendEmailWithErrorHandling($pdfPath, $emailParticipante);

                return $response->json();
            } else {
                Log::error('Error al enviar constancia por WhatsApp: ' . $response->body(), [
                    'phoneNumberId' => $this->phoneNumberId,
                    'pedidoCurso' => json_encode($this->pedidoCurso),
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
                'pedidoCurso' => json_encode($this->pedidoCurso),
                'error' => $e->getTraceAsString()
            ]);



            $this->fail($e);
        } finally {
            // Eliminar el archivo PDF temporal si existe
            if ($pdfPath && file_exists($pdfPath)) {
                unlink($pdfPath);
                Log::info('Archivo PDF temporal eliminado', ['path' => $pdfPath]);
            }
        }
    }

    private function generatePDF(string $nombre, string $fecha): string
    {
        try {
            //encode base img
            $fondoImg = base64_encode(file_get_contents(public_path('img/fondo.png')));
            //convert fecha d/m/y to day de month de year
            $fecha = date('d \d\e F \d\e Y', strtotime($fecha));
            //reemplazar mes en español
            $meses = [
                'January' => 'Enero',
                'February' => 'Febrero',
                'March' => 'Marzo',
                'April' => 'Abril',
                'May' => 'Mayo',
                'June' => 'Junio',
                'July' => 'Julio',
                'August' => 'Agosto',
                'September' => 'Septiembre',
                'October' => 'Octubre',
                'November' => 'Noviembre',
                'December' => 'Diciembre'
            ];
            $fecha = str_replace(array_keys($meses), array_values($meses), $fecha);


            // Crear el PDF
            $pdf = PDF::loadView('constancia', [
                'nombre' => $nombre,
                'fecha' => $fecha,
                'fondoImg' => $fondoImg
            ]);
            // $fontMetrics = $pdf->getFontMetrics();
            // $fontMetrics->registerFont(
            //     ['family' => 'lucide-handwriting', 'style' => 'normal', 'weight' => 'normal'],
            //     storage_path('fonts/lucide-handwriting-regular.ttf')
            // );

            // // Configurar el PDF tamaño carta horizontal
            // $pdf->setPaper('letter', 'landscape');
            //load fonts

            $options = new Options();
            $options->set('fontDir', storage_path('fonts/'));
            $options->set('fontCache', storage_path('fonts/'));
            $options->set('isHtml5ParserEnabled', true);
            $options->set('isRemoteEnabled', false);
            //set paper letter landscape
            $options->set('defaultPaperSize', 'letter');
            $options->set('defaultPaperOrientation', 'landscape');
            $dompdf = new Dompdf($options);

            // Registrar la fuente
            $fontMetrics = $dompdf->getFontMetrics();
            $fontMetrics->registerFont(
                ['family' => 'lucide-handwriting', 'style' => 'normal', 'weight' => 'normal'],
                storage_path('fonts/lucide-handwriting-regular.ttf')
            );

            $dompdf = PDF::loadView('constancia', [
                'nombre' => $nombre,
                'fecha' => $fecha,
                'fondoImg' => $fondoImg
            ]);

            // $dompdf->loadHtml(string: $html);
            // $dompdf->setPaper('A4', 'portrait');
            $fileName = 'constancia_' . Str::slug($nombre) . '_' . time() . '.pdf';
            $pdfPath = storage_path('app/temp/' . $fileName);

            // Asegurar que el directorio existe
            if (!file_exists(dirname($pdfPath))) {
                mkdir(dirname($pdfPath), 0755, true);
            }
            //set dpi 150
            $dompdf->set_option('dpi', 150);
            $dompdf->setPaper('letter', 'landscape');
            // Guardar el PDF
            $dompdf->render();
            file_put_contents($pdfPath, $dompdf->output());

            Log::info('PDF generado exitosamente', [
                'path' => $pdfPath,
                'nombre' => $nombre,
                'size' => filesize($pdfPath) . ' bytes'
            ]);

            return $pdfPath;
        } catch (\Exception $e) {
            Log::error('Error generando PDF: ' . $e->getMessage());
            throw new \Exception('Error al generar la constancia PDF: ' . $e->getMessage());
        }
    }

    private function sendPDFToWhatsApp(string $pdfPath)
    {
        $fileName = basename($pdfPath);
        $mensaje = "🎓 ¡Felicitaciones! Aquí tienes tu constancia del Taller Virtual de Importación.\n\n" .
            "Equivalente a 16 horas académicas.\n" .
            "Dictado por el docente Miguel Villegas.\n\n" .
            "¡Gracias por tu participación! 🎉";

        return Http::timeout(30)
            ->asMultipart()
            ->post($this->apiUrl, [
                [
                    'name' => 'numero',
                    'contents' => $this->phoneNumberId
                ],
                [
                    'name' => 'mensaje',
                    'contents' => $mensaje
                ],
                [
                    'name' => 'archivo',
                    'contents' => fopen($pdfPath, 'r'),
                    'filename' => $fileName,
                    'headers' => [
                        'Content-Type' => 'application/pdf'
                    ]
                ]
            ]);
    }

    private function sendEmailWithErrorHandling(string $pdfPath, string $emailParticipante)
    {
        try {
            Mail::raw('🎓 ¡Felicitaciones! Aquí tienes tu constancia del Taller Virtual de Importación.

                    Equivalente a 16 horas académicas.
                    Dictado por el docente Miguel Villegas.
                    
                    ¡Gracias por tu participación! ', function ($message) use ($pdfPath, $emailParticipante) {
                                            $message->from('noreply@lae.one', 'Probusiness')
                                                    ->to($emailParticipante)
                                                    ->subject('Constancia de Curso-Probusiness')
                                                    ->attach($pdfPath, [
                                                        'as' => basename($pdfPath),
                                                        'mime' => 'application/pdf'
                                                    ]);
                                        });
            Log::info('Correo enviado exitosamente', ['email' => $emailParticipante]);
        } catch (\Exception $e) {
            Log::error('Error al enviar correo: ' . $e->getMessage(), [
                'email' => $emailParticipante,
                'error' => $e->getTraceAsString()
            ]);
            // NO lanzar excepción - el job debe continuar como exitoso
        }
    }

    public function tags()
    {
        return [
            'send-constancia-curso-job',
            'phoneNumberId:' . $this->phoneNumberId,
            'pedidoCurso:' . json_encode($this->pedidoCurso->No_Entidad) // Asegúrate de que esto no sea demasiado grande
        ];
    }

    public function failed(\Throwable $exception)
    {
        Log::error('Job SendConstanciaCurso falló completamente', [
            'phoneNumberId' => $this->phoneNumberId,
            'pedidoCurso' => $this->pedidoCurso,
            'error' => $exception->getMessage()
        ]);
    }
}
