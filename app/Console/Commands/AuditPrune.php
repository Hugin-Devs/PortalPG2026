<?php

namespace App\Console\Commands;

use App\Models\ActivityLog;
use App\Support\Audit;
use Illuminate\Console\Command;

/**
 * Elimina registros de auditoría (activity_logs) más antiguos que la retención
 * configurada. La ventana por defecto (400 días ≈ 13 meses) cubre auditorías
 * de ciclo anual completo.
 *
 * Programado a diario en app/Console/Kernel.php. El propio borrado queda
 * registrado como evento de auditoría (audit.pruned) salvo en dry-run.
 */
class AuditPrune extends Command
{
    protected $signature = 'audit:prune {--days= : Sobrescribe la retención configurada} {--dry-run : Muestra cuántos registros se eliminarían sin escribir}';

    protected $description = 'Elimina los registros de auditoría fuera de la ventana de retención';

    public function handle(): int
    {
        $days = max(1, (int) ($this->option('days') ?? config('security.audit_retention_days', 400)));
        $cutoff = now()->subDays($days);

        $query = ActivityLog::where('created_at', '<', $cutoff);
        $count = (clone $query)->count();

        if ($this->option('dry-run')) {
            $this->info("Dry-run: {$count} registro(s) de auditoría más antiguos que {$days} días serían eliminados.");

            return self::SUCCESS;
        }

        $query->delete();

        if ($count > 0) {
            Audit::log('audit.pruned', ActivityLog::class, null, "Retención: {$count} registro(s) de auditoría eliminados", [
                'days' => $days,
            ]);
        }

        $this->info("Retención aplicada: {$count} registro(s) eliminados (ventana de {$days} días).");

        return self::SUCCESS;
    }
}
