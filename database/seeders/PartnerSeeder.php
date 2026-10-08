<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PartnerSeeder extends Seeder
{
    /**
     * Borrador — pendiente de contenido.
     * Los logos NO se siembran aquí: se cargan desde /admin/entes (upload).
     * Estructura de cada entrada:
     *   ['name' => '...', 'description' => '...', 'logo' => null, 'url' => 'https://...', 'order' => 1, 'is_active' => true]
     * El slug se genera automáticamente con Str::slug(name).
     */
    public function run(): void
    {
        $partners = [
            // ['name' => 'Institución Ejemplo', 'description' => 'Descripción corta del ente.', 'logo' => null, 'url' => 'https://ejemplo.gob.ve', 'order' => 1, 'is_active' => true],
        ];

        foreach ($partners as $attrs) {
            Partner::updateOrCreate(
                ['name' => $attrs['name']],
                $attrs + ['slug' => Str::slug($attrs['name'])]
            );
        }
    }
}
