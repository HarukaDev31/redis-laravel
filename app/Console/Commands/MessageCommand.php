<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use App\Jobs\SendSimpleMessageJobCron;

class MessageCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'message:start';

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
    private $table = 'contenedor_consolidado_cotizacion_crons';
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
            Log::info('Starting the message command...');
            Log::info('Fetching data from the database...');
            $data = DB::table($this->table)
                ->whereNull('executed_at')->where(
                    'execution_at',
                    '<=',
                    date('Y-m-d H:i:s')
                )->get();
            Log::info('Data fetched from the database.' . json_encode([
                'count' => $data->count(),
                'data' => $data
            ]));
            $data = $data->map(function ($item) {
                $data_json = json_decode($item->data_json, true);
                Log::info('Data JSON decoded.', [
                    'data_json' => $data_json
                ]);
                return [
                    'message' => $data_json['message'] ?? '',
                    'phoneNumberId' => $data_json['phoneNumberId'] ?? '',
                    'id' => $item->id,
                ];
            });
            Log::info('Data fetched successfully.');
            foreach ($data as $item) {
                $message = $item['message'];
                $phoneNumberId = $item['phoneNumberId'];
                $id = $item['id'];
                Log::info('Sending message...', [
                    'message' => $message,
                    'phoneNumberId' => $phoneNumberId,
                    'id' => $id,
                ]);

                SendSimpleMessageJobCron::dispatch($message, $phoneNumberId, $id);
            }
            return 0;
        } catch (\Exception $e) {
            Log::error('Error in message command: ' . $e->getMessage(), [
                'trace' => $e->getTraceAsString()
            ]);
            return 1;
        }
    }
}
