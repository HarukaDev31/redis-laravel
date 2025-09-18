<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     *
     * @param  \Illuminate\Console\Scheduling\Schedule  $schedule
     * @return void
     */
    protected function schedule(Schedule $schedule)
    {
        // call the command every 5 minutes message:start
//        $schedule->command('message:start')->everyFiveMinutes();
        // $schedule->command('message:start')->everyFiveMinutes();
        //$schedule->command('curso:start')->cron('0 3 * * *'); // Runs daily at 3 AM
        $schedule->command('horizon:snapshot')->everyFiveMinutes();
        // $schedule->command('inspire')->hourly();
    }

    /**
     * Register the commands for the application.
     *
     * @return void
     */
    protected function commands()
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
