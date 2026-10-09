<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Cifra en reposo los datos personales (PII) existentes que siguen en texto plano.
 *
 * Necesario tras activar los casts `encrypted` en Contact/User: la migración de
 * esquema solo amplía las columnas a `text`, no re-cifra las filas ya creadas.
 * Sin este comando, cualquier registro anterior a la migración provocaría un
 * `DecryptException` al leerlo con el cast activo.
 *
 * Uso:
 *   php artisan pii:encrypt            # cifra lo que falte
 *   php artisan pii:encrypt --dry-run  # solo informa, no escribe
 *
 * El comando es idempotente: detecta valores ya cifrados y los salta.
 */
class EncryptPii extends Command
{
    protected $signature = 'pii:encrypt {--dry-run : Muestra el trabajo pendiente sin escribir}';

    protected $description = 'Cifra en reposo los datos personales (PII) que siguen en texto plano';

    /**
     * Tabla => columnas con cast `encrypted` en sus modelos.
     */
    protected array $targets = [
        'contacts' => ['name', 'email', 'subject', 'message'],
        'users' => ['name'],
    ];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $total = 0;

        foreach ($this->targets as $table => $columns) {
            foreach ($columns as $column) {
                $total += $this->encryptColumn($table, $column, $dryRun);
            }
        }

        if ($dryRun) {
            $this->info("Dry-run: {$total} valor(es) en texto plano serian cifrados.");
        } elseif ($total === 0) {
            $this->info('No hay valores en texto plano: todo el PII ya esta cifrado.');
        } else {
            $this->info("PII cifrado: {$total} valor(es) actualizado(s).");
        }

        return self::SUCCESS;
    }

    /**
     * Recorre una columna y re-cifra solo los valores que siguen en texto plano.
     */
    protected function encryptColumn(string $table, string $column, bool $dryRun): int
    {
        $updated = 0;

        DB::table($table)
            ->orderBy('id')
            ->chunkById(100, function ($rows) use ($table, $column, $dryRun, &$updated) {
                foreach ($rows as $row) {
                    $raw = $row->{$column} ?? null;

                    if ($raw === null || $raw === '' || $this->isEncrypted((string) $raw)) {
                        continue;
                    }

                    if (! $dryRun) {
                        DB::table($table)
                            ->where('id', $row->id)
                            ->update([$column => Crypt::encrypt((string) $raw, false)]);
                    }

                    $updated++;
                }
            });

        if ($updated > 0) {
            $this->line("  {$table}.{$column}: {$updated} valor(es)");
        }

        return $updated;
    }

    /**
     * Determina si un valor almacenado ya esta cifrado con el cast `encrypted`
     * de Laravel (Crypt::encrypt($value, false)).
     */
    protected function isEncrypted(string $value): bool
    {
        try {
            Crypt::decrypt($value, false);

            return true;
        } catch (Throwable) {
            return false;
        }
    }
}
