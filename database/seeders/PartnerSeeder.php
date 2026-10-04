<?php

namespace Database\Seeders;

use App\Models\Partner;
use Illuminate\Database\Seeder;

class PartnerSeeder extends Seeder
{
    /**
     * Borrador — pendiente de contenido.
     * Los logos NO se siembran aquí: se cargan desde /admin/entes (upload).
     * Estructura de cada entrada:
     *   ['name' => '...', 'logo' => null, 'url' => 'https://...', 'order' => 1, 'is_active' => true]
     */
    public function run(): void
    {
        $partners = [
            // ['name' => 'Institución Ejemplo', 'logo' => null, 'url' => 'https://ejemplo.gob.ve', 'order' => 1, 'is_active' => true],
        ];

        foreach ($partners as $attrs) {
            Partner::updateOrCreate(['name' => $attrs['name']], $attrs);
        }
    }
}
