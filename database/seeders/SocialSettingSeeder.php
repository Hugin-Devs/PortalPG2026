<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SocialSettingSeeder extends Seeder
{
    public const KEYS = [
        'social_facebook',
        'social_instagram',
        'social_youtube',
        'social_x',
        'social_threads',
        'social_telegram',
        'social_whatsapp',
    ];

    public function run(): void
    {
        foreach (self::KEYS as $key) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => '']
            );
        }
    }
}
