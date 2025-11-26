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
                //where month of Fe_Fin is september or october or november
                ->whereMonth('Fe_Fin', '=', date('m'))
                ->where('send_constancia', 'PENDING')

                ->get();
            //get pedidos id_campana in campanas send_constancia is PENDING and tipo_curso is 1 and Nu_Estado is 2 and join with entidad and get Nu_Celular_Entidad
            $pedidos = DB::table($this->table_pedido_curso)
                ->whereIn('pedido_curso.ID_Campana', $campanas->pluck('ID_Campana'))
                ->where('pedido_curso.tipo_curso', 1)
                ->where('pedido_curso.Nu_Estado', 2)
                ->join($this->table_campana, 'pedido_curso.ID_Campana', '=', 'campana_curso.ID_Campana')
                ->join($this->table_entidad, 'pedido_curso.ID_Entidad', '=', 'entidad.ID_Entidad')
                ->join($this->table_usuario, 'entidad.ID_Entidad', '=', 'usuario.ID_Entidad')
                ->select('pedido_curso.ID_Pedido_Curso', 'entidad.Nu_Celular_Entidad', 'entidad.No_Entidad','entidad.Txt_Email_Entidad', 'Fe_Fin')
                ->get();
            Log::info('Found ' . $pedidos->count() . ' pedidos to process.');
            Log::info('Processing each pedido...');
            foreach ($pedidos as $pedido) {
                Log::info('Processing pedido ID: ' . json_encode($pedido));
                //call job SendSimpleMessageJobCron
                SendConstanciaCurso::dispatch(
                    $pedido->Nu_Celular_Entidad,
                    $pedido
                );
            }
            if ($pedidos->isEmpty()) {
                Log::info('No pending pedidos found.');
                return 0; // No hay pedidos pendientes
            }
        } catch (\Exception $e) {
            Log::error('Error fetching data: ' . $e->getMessage());
            return 1;
        }
    }
}
