<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| Console Routes
|--------------------------------------------------------------------------
|
| This file is where you may define all of your Closure based console
| commands. Each Closure is bound to a command instance allowing a
| simple approach to interacting with each command's IO methods.
|
*/

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Retención de PII: mensajes de contacto fuera de ventana (GDPR).
Schedule::command('contacts:prune')->dailyAt('03:17');
// Retención de logs de auditoría (ventana por defecto 400 días).
Schedule::command('audit:prune')->dailyAt('04:23');
