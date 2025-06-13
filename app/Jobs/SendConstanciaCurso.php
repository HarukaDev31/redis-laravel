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
use PDF; // Asegúrate de tener DomPDF instalado: composer require barryvdh/laravel-dompdf
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

            

          

            // Obtener nombre del participante (ajusta según tu estructura de BD)
            $nombreParticipante = $this->pedidoCurso->No_Entidad ?? 'Participante';
            $fechaEmision = now();

            // Generar PDF desde el Blade
            $pdfPath = $this->generatePDF($nombreParticipante, $fechaEmision);

            // Enviar el PDF por WhatsApp
            $response = $this->sendPDFToWhatsApp($pdfPath);

            if ($response->successful()) {
                // Actualizar estado a ENVIADO
                DB::table($this->table)
                    ->where('ID_Pedido_Curso', $this->pedidoCurso->ID_Pedido_Curso)
                    ->update([
                        'send_constancia' => 'SENDED',
                        
                    ]);

                Log::info('Constancia enviada exitosamente', [
                    'phoneNumberId' => $this->phoneNumberId,
                    'pedidoCurso' => json_encode($this->pedidoCurso),
                ]);

                return $response->json();
            } else {
                throw new \Exception("Error al enviar constancia: " . $response->body());
            }

        } catch (\Exception $e) {
            Log::error('Error en SendConstanciaCurso: ' . $e->getMessage(), [
                'phoneNumberId' => $this->phoneNumberId,
                'pedidoCurso' =>json_encode($this->pedidoCurso),
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
            $html = view('constancia', [
                'nombre' => $nombre,
                'fecha' => $fecha,
                'fondoImg' => $fondoImg
            ])->render();

            // Crear el PDF
            $pdf = PDF::loadHTML($html);
            
            // Configurar el PDF tamaño carta horizontal
            $pdf->setPaper('letter', 'landscape'); // Tamaño carta horizontal para constancias
            //without margins
            $pdf->setOptions([
                'dpi' => 150,
                'defaultFont' => 'sans-serif',
                'isRemoteEnabled' => true, // Para cargar imágenes remotas
                'isHtml5ParserEnabled' => true
            ]);

            // Generar nombre único para el archivo
            $fileName = 'constancia_' . Str::slug($nombre) . '_' . time() . '.pdf';
            $pdfPath = storage_path('app/temp/' . $fileName);

            // Asegurar que el directorio existe
            if (!file_exists(dirname($pdfPath))) {
                mkdir(dirname($pdfPath), 0755, true);
            }

            // Guardar el PDF
            $pdf->save($pdfPath);

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