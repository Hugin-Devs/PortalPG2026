<?php

namespace App\Console\Commands;

use App\Models\Contact;
use App\Support\Audit;
use Illuminate\Console\Command;

/**
 * Elimina mensajes de contacto más antiguos que la retención configurada
 * (GDPR Art. 5(1)(e) — limitación de la conservación).
 *
 * Programado a diario en app/Console/Kernel.php.
 */
class ContactsPrune extends Command
{
    protected $signature = 'contacts:prune {--dry-run : Muestra cuántos mensajes se eliminarían sin escribir}';

    protected $description = 'Elimina los mensajes de contacto fuera de la ventana de retención';

    public function handle(): int
    {
        $days = max(1, (int) config('security.contact_retention_days', 365));
        $cutoff = now()->subDays($days);

        $query = Contact::where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Dry-run: {$count} mensaje(s) más antiguos que {$days} días serían eliminados.");

            return self::SUCCESS;
        }

        $query->delete();

        if ($count > 0) {
            Audit::log('contacts.pruned', Contact::class, null, "Retención: {$count} mensaje(s) de contacto eliminados", [
                'days' => $days,
            ]);
        }

        $this->info("Retención aplicada: {$count} mensaje(s) eliminados (ventana de {$days} días).");

        return self::SUCCESS;
    }
}
