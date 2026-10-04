<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class ContactSettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            'contact_address' => 'Av. Principal, Distrito Capital',
            'contact_phone' => '+58 212-XXX-XXXX',
            'contact_email' => 'contacto@gdc.gob.ve',
            'contact_hours' => 'Lunes a viernes: 8:00 AM - 4:00 PM',
            'contact_map_url' => 'https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d283.27263825321114!2d-66.91497767711432!3d10.506585216453733!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8c2a5f4f6e625ba7%3A0x6e9963acb779ffdc!2sEdificio%20Toromaima%20-%20G.D.C.!5e0!3m2!1ses!2sve!4v1674657617845!5m2!1ses!2sve',
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
