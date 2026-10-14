<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SocialSettingSeeder extends Seeder
{
    /**
     * Redes sociales mostradas como íconos en el pie del sitio.
     *
     * ── CARGAR LAS URLs REALES ───────────────────────────────────────────
     * Pega aquí las URLs de la institución, UNA POR LÍNEA.
     * Varias líneas = varias cuentas del mismo tipo (se dibuja un icono
     * por línea; el tooltip distingue: "Facebook — https://...").
     *
     * Formato (usa saltos de línea reales dentro de las comillas dobles):
     *   'social_facebook' => "https://www.facebook.com/perfil1\nhttps://www.facebook.com/perfil2",
     *
     * Reglas: cada línea debe ser una URL http(s) completa; si el campo
     * queda '' se muestra sin icono de esa red. También se puede editar
     * desde /admin/rrss (textarea, misma regla una URL por línea).
     *
     * Idempotente y no destructivo: crea las 7 claves faltantes y rellena
     * SOLO los valores vacíos; nunca pisa lo editado desde el admin
     * (lo comprueba la suite test-social.ps1).
     */
    public const REDES = [
        'social_facebook' => '',
        'social_instagram' => '',
        'social_youtube' => '',
        'social_x' => '',
        'social_threads' => '',
        'social_telegram' => '',
        'social_whatsapp' => '',
    ];

    public function run(): void
    {
        foreach (static::REDES as $key => $value) {
            $setting = Setting::where('key', $key)->first();

            if (! $setting) {
                Setting::create(['key' => $key, 'value' => $value]);
            } elseif (trim((string) $setting->value) === '' && trim($value) !== '') {
                $setting->update(['value' => $value]);
            }
        }
    }
}
