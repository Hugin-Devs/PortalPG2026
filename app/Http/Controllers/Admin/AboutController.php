<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Http\Request;

class AboutController extends Controller
{
    public function index()
    {
        $history = Setting::where('key', 'about_history')->first()?->value ?? '';
        $mission = Setting::where('key', 'about_mission')->first()?->value ?? '';
        $vision = Setting::where('key', 'about_vision')->first()?->value ?? '';

        return view('admin.about.index', compact('history', 'mission', 'vision'));
    }

    public function update(Request $request)
    {
        $data = $request->validate([
            'history' => 'nullable|string|max:200000',
            'mission' => 'nullable|string|max:200000',
            'vision' => 'nullable|string|max:200000',
        ]);

        foreach (['history' => 'about_history', 'mission' => 'about_mission', 'vision' => 'about_vision'] as $field => $key) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $data[$field] ?? '']
            );
        }

        Audit::log('about.updated', null, null, 'Contenido de Sobre Nosotros actualizado');

        return redirect('/admin/nosotros')->with('success', 'Contenido actualizado correctamente.');
    }
}
