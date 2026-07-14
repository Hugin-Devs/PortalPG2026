<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
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
            'history' => 'nullable',
            'mission' => 'nullable',
            'vision' => 'nullable',
        ]);

        foreach (['history' => 'about_history', 'mission' => 'about_mission', 'vision' => 'about_vision'] as $field => $key) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $data[$field] ?? '']
            );
        }

        return redirect('/admin/nosotros')->with('success', 'Contenido actualizado correctamente.');
    }
}
