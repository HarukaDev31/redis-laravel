<?php

namespace App\Console\Commands;

use App\Jobs\SendConstanciaCurso;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendSimpleMessageJobCron;

class CursoCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'curso:start';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    private $table_campana = 'campana_curso';
    private $table_pedido_curso = 'pedido_curso';
    private $table_entidad = 'entidad';
    private $table_usuario = 'usuario';
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        try {
            //get all campanas where Fe_Borrado is null get all pedidos where Fe_Borrado is null Fe_Fin and ID_Campana
            Log::info('Starting the curso command...');
            Log::info('Fetching data from the database...');
            $campanas = DB::table($this->table_campana)
                ->whereNull('Fe_Borrado')
                ->where('Fe_Fin', '>=', now())
                ->whereYear('Fe_Fin', date('Y'))
                ->get();
            return;
            Log::info('Found ' . $campanas->count() . ' campanas.');
            Log::info('Campanas: ' . json_encode($campanas));
            // Primero obtener todos los pedidos que cumplan las condiciones básicas
            $pedidosQuery = DB::table($this->table_pedido_curso . ' AS CC')
                ->whereIn('CC.ID_Campana', $campanas->pluck('ID_Campana'))
                ->where('CC.tipo_curso', 1)
                ->where('CC.Nu_Estado', 2)
                ->where('CC.send_constancia', 'PENDING')
                ->where('campana_curso.Fe_Fin', '>=', now())
                ->join($this->table_campana, 'CC.ID_Campana', '=', 'campana_curso.ID_Campana')
                ->join($this->table_entidad, 'CC.ID_Entidad', '=', 'entidad.ID_Entidad')
                ->join($this->table_usuario, 'entidad.ID_Entidad', '=', 'usuario.ID_Entidad')
                ->select([
                    'CC.ID_Pedido_Curso',
                    'CC.Ss_Total',
                    'entidad.Nu_Celular_Entidad',
                    'entidad.No_Entidad',
                    'entidad.Txt_Email_Entidad',
                    'campana_curso.Fe_Fin',
                    DB::raw('(
                        SELECT IFNULL(SUM(cccp.monto), 0)
                        FROM pedido_curso_pagos as cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = CC.ID_Pedido_Curso
                        AND ccp.name = "ADELANTO"
                        AND cccp.status = "CONFIRMADO"
                    ) AS total_pagos'),
                    DB::raw('(
                        SELECT COUNT(*)
                        FROM pedido_curso_pagos as cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = CC.ID_Pedido_Curso
                        AND ccp.name = "ADELANTO"
                    ) AS total_pagos_adelanto'),
                    DB::raw('(
                        SELECT COUNT(*)
                        FROM pedido_curso_pagos as cccp
                        JOIN pedido_curso_pagos_concept ccp ON cccp.id_concept = ccp.id
                        WHERE cccp.id_pedido_curso = CC.ID_Pedido_Curso
                        AND ccp.name = "ADELANTO"
                        AND cccp.status = "CONFIRMADO"
                    ) AS pagos_confirmados')
                ]);
            
            $allPedidos = $pedidosQuery->get();
            Log::info('Found ' . $allPedidos->count() . ' pedidos before filtering by payments.');
            
            // Filtrar en PHP para mejor debugging
            $pedidos = $allPedidos->filter(function ($pedido) {
                $totalPagos = (float) $pedido->total_pagos;
                $totalCurso = (float) $pedido->Ss_Total;
                $totalPagosAdelanto = (int) $pedido->total_pagos_adelanto;
                $pagosConfirmados = (int) $pedido->pagos_confirmados;
                
                $hasPagos = $totalPagos > 0;
                $allPagosCONFIRMADO = $totalPagosAdelanto > 0 && $totalPagosAdelanto === $pagosConfirmados;
                $totalMatches = round($totalPagos, 2) === round($totalCurso, 2);
                
                Log::info('Pedido ID: ' . $pedido->ID_Pedido_Curso . 
                    ' - Total pagado: ' . $totalPagos . 
                    ' - Total curso: ' . $totalCurso . 
                    ' - Total adelanto: ' . $totalPagosAdelanto . 
                    ' - Confirmados: ' . $pagosConfirmados .
                    ' - Has pagos: ' . ($hasPagos ? 'YES' : 'NO') .
                    ' - All CONFIRMADO: ' . ($allPagosCONFIRMADO ? 'YES' : 'NO') .
                    ' - Total matches: ' . ($totalMatches ? 'YES' : 'NO'));
                
                return $hasPagos && $allPagosCONFIRMADO && $totalMatches;
            });
            
            Log::info('Found ' . $pedidos->count() . ' pedidos to process after filtering.');
            Log::info('Processing each pedido...');
            
            foreach ($pedidos as $pedido) {
                Log::info('Processing pedido ID: ' . $pedido->ID_Pedido_Curso . ' - Total pagado: ' . $pedido->total_pagos . ' - Total curso: ' . $pedido->Ss_Total);
                //call job SendConstanciaCurso
                SendConstanciaCurso::dispatch(
                    $pedido->Nu_Celular_Entidad,
                    $pedido
                );
            }
            
            if ($pedidos->isEmpty()) {
                Log::info('No pending pedidos found with CONFIRMADO payments.');
                return 0; // No hay pedidos pendientes
            }
            
            Log::info('Successfully queued ' . $pedidos->count() . ' constancias to send.');
            return 0;
        } catch (\Exception $e) {
            Log::error('Error fetching data: ' . $e->getMessage());
            Log::error('Stack trace: ' . $e->getTraceAsString());
            return 1;
        }
    }
}
