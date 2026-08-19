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
        $schedule->command('LinnworksTokenRefresh:task')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('IntegrationProcessedOrders:task')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('klaviyo:refresh-token')->everyFiveMinutes()->withoutOverlapping();
        $schedule->command('MercadoLibreTokenRefresh:task')->hourly()->withoutOverlapping();
        $schedule->command('MercadoLibreSync:task')->everyFifteenMinutes()->withoutOverlapping();
        $schedule->command('MercadoLibreInventorySync:task')->everyFifteenMinutes()->withoutOverlapping();
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
