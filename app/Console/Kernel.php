<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    /**
     * Define the application's command schedule.
     */
    protected function schedule(Schedule $schedule): void
    {
        // Retención de PII: mensajes de contacto fuera de ventana (GDPR).
        $schedule->command('contacts:prune')->dailyAt('03:17');
        // Retención de logs de auditoría (ventana por defecto 400 días).
        $schedule->command('audit:prune')->dailyAt('04:23');
    }

    /**
     * Register the commands for the application.
     */
    protected function commands(): void
    {
        $this->load(__DIR__.'/Commands');

        require base_path('routes/console.php');
    }
}
