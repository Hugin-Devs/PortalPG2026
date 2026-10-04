<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;

class SettingController extends Controller
{
    private const SOCIAL_FIELDS = [
        'facebook' => 'social_facebook',
        'instagram' => 'social_instagram',
        'youtube' => 'social_youtube',
        'x' => 'social_x',
        'threads' => 'social_threads',
        'telegram' => 'social_telegram',
        'whatsapp' => 'social_whatsapp',
    ];

    public function index()
    {
        $social = [];
        foreach (self::SOCIAL_FIELDS as $field => $key) {
            $social[$field] = Setting::where('key', $key)->first()?->value ?? '';
        }

        return view('admin.settings.index', ['social' => $social]);
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (array_keys(self::SOCIAL_FIELDS) as $field) {
            $rules[$field] = 'nullable|string|url|max:255';
        }
        $data = $request->validate($rules);

        foreach (self::SOCIAL_FIELDS as $field => $key) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => trim($data[$field] ?? '')]
            );
        }

        return redirect('/admin/configuracion')->with('success', 'Redes sociales actualizadas correctamente.');
    }
}
