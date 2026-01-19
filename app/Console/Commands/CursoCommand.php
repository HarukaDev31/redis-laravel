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
                ->get();
            $pedidos = DB::table($this->table_pedido_curso . ' AS CC')
                ->whereIn('CC.ID_Campana', $campanas->pluck('ID_Campana'))
                ->where('CC.tipo_curso', 1)
                ->where('CC.Nu_Estado', 2)
                ->where('CC.send_constancia', 'PENDING')
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
                        AND cccp.status = "CONFIRMED"
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
                        AND cccp.status = "CONFIRMED"
                    ) AS pagos_confirmados')
                ])
                ->havingRaw('total_pagos > 0') // Debe tener al menos un pago
                ->havingRaw('total_pagos_adelanto = pagos_confirmados') // Todos los pagos deben estar confirmados
                ->havingRaw('ROUND(total_pagos, 2) = ROUND(CC.Ss_Total, 2)') // El total pagado debe ser igual al total del curso
                ->get();
            
            Log::info('Found ' . $pedidos->count() . ' pedidos to process.');
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
                Log::info('No pending pedidos found with confirmed payments.');
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
