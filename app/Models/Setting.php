<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    use HasFactory;

    protected $fillable = ['key', 'value'];

    /**
     * Configuración general del sitio (panel /admin/configuracion).
     * Los valores por defecto permiten que el sitio funcione sin filas en settings.
     */
    public static function defaults(): array
    {
        return [
            'site_name' => 'Portal GDC',
            'site_logo' => '',
            'nav_funcionarios_url' => 'https://www.gdc.gob.ve/extranet/',
            'nav_jubilados_url' => '',
        ];
    }

    /**
     * Lee la configuración general: una sola query; una fila existente con
     * valor vacío SIEMPRE gana al default (así se ocultan ítems del navbar).
     */
    public static function values(?array $keys = null): array
    {
        $defaults = self::defaults();
        $keys = $keys ?? array_keys($defaults);
        $rows = self::whereIn('key', $keys)->pluck('value', 'key')->all();

        $out = [];
        foreach ($keys as $key) {
            $out[$key] = array_key_exists($key, $rows) ? (string) $rows[$key] : ($defaults[$key] ?? '');
        }

        return $out;
    }
}
