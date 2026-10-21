<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Support\Audit;
use Illuminate\Http\Request;

class RrssController extends Controller
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

        return view('admin.rrss.index', ['social' => $social]);
    }

    public function update(Request $request)
    {
        $rules = [];
        foreach (array_keys(self::SOCIAL_FIELDS) as $field) {
            $rules[$field] = [
                'nullable',
                'string',
                'max:4000',
                function (string $attribute, mixed $value, \Closure $fail): void {
                    foreach (self::splitLines((string) $value) as $i => $line) {
                        if (! filter_var($line, FILTER_VALIDATE_URL) || ! preg_match('#^https?://#i', $line)) {
                            $fail('La línea '.($i + 1).' no es una URL válida (debe empezar por https://).');

                            return;
                        }
                    }
                },
            ];
        }
        $data = $request->validate($rules);

        foreach (self::SOCIAL_FIELDS as $field => $key) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => implode("\n", self::splitLines($data[$field] ?? ''))]
            );
        }

        Audit::log('rrss.updated', null, null, 'Redes sociales actualizadas');

        return redirect('/admin/rrss')->with('success', 'Redes sociales actualizadas correctamente.');
    }

    /**
     * Una URL por línea: acepta saltos CRLF/LF/CR, recorta y descarta vacías.
     *
     * @return array<int, string>
     */
    private static function splitLines(string $value): array
    {
        $lines = preg_split('/\r\n|\r|\n/', trim($value)) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn ($l) => $l !== ''));
    }
}
