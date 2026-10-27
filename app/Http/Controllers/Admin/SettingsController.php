<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Webp;
use App\Support\Audit;
use App\Support\Uploads;
use Illuminate\Http\Request;

class SettingsController extends Controller
{
    public function index()
    {
        $settings = Setting::values();

        return view('admin.settings.index', [
            'settings' => $settings,
            'hasCustomLogo' => $settings['site_logo'] !== '',
            'logoUrl' => asset($settings['site_logo'] !== '' ? 'storage/'.$settings['site_logo'] : 'images/logo.png'),
        ]);
    }

    public function update(Request $request)
    {
        $validateLink = function (string $attribute, mixed $value, \Closure $fail): void {
            $value = trim((string) $value);
            if ($value === '') {
                return;
            }

            if (preg_match('#^https?://#i', $value)) {
                if (filter_var($value, FILTER_VALIDATE_URL) === false) {
                    $fail('La URL no es válida: debe comenzar con http:// o https://.');
                }

                return;
            }

            if (! str_starts_with($value, '/')) {
                $fail('La ruta del servidor debe comenzar con "/" (ej.: /documentos/convocatorias.pdf).');

                return;
            }

            if (str_contains($value, '..') || str_contains($value, '\\') || str_contains($value, "\0")) {
                $fail('La ruta del servidor no puede contener ".." ni caracteres no permitidos.');

                return;
            }

            if (! file_exists(public_path(ltrim($value, '/')))) {
                $fail('El archivo o directorio no existe dentro de la raíz pública (public/).');
            }
        };

        $data = $request->validate([
            'site_name' => ['required', 'string', 'max:100'],
            'nav_funcionarios_url' => ['nullable', 'string', 'max:255', $validateLink],
            'nav_jubilados_url' => ['nullable', 'string', 'max:255', $validateLink],
            'nav_intranet_url' => ['nullable', 'string', 'max:255', $validateLink],
            'logo' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
            'remove_logo' => ['nullable', 'boolean'],
        ]);

        Setting::updateOrCreate(['key' => 'site_name'], ['value' => trim($data['site_name'])]);
        Setting::updateOrCreate(['key' => 'nav_funcionarios_url'], ['value' => trim($data['nav_funcionarios_url'] ?? '')]);
        Setting::updateOrCreate(['key' => 'nav_jubilados_url'], ['value' => trim($data['nav_jubilados_url'] ?? '')]);
        Setting::updateOrCreate(['key' => 'nav_intranet_url'], ['value' => trim($data['nav_intranet_url'] ?? '')]);

        if ($request->hasFile('logo')) {
            $this->deleteLogo();
            $file = $request->file('logo');
            $ext = Uploads::safeExtension($file, ['jpg', 'jpeg', 'png', 'gif', 'webp']);
            $logoPath = $file->storeAs('settings', 'logo.'.$ext, 'public');
            Webp::convert($logoPath);
            Setting::updateOrCreate(['key' => 'site_logo'], ['value' => $logoPath]);
        } elseif ($request->boolean('remove_logo')) {
            $this->deleteLogo();
            Setting::updateOrCreate(['key' => 'site_logo'], ['value' => '']);
        }

        Audit::log('settings.updated', null, null, 'Configuración general actualizada (identidad/enlaces/logo)');

        return redirect('/admin/configuracion')->with('success', 'Configuración actualizada correctamente.');
    }

    private function deleteLogo(): void
    {
        $old = Setting::where('key', 'site_logo')->first()?->value;
        if ($old) {
            \Storage::disk('public')->delete($old);
            Webp::delete($old);
        }
    }
}
