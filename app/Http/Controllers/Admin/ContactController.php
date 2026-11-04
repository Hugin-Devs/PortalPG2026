<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Setting;
use App\Support\Audit;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    private const FIELDS = [
        'address' => 'contact_address',
        'phone' => 'contact_phone',
        'email' => 'contact_email',
        'hours' => 'contact_hours',
        'map_url' => 'contact_map_url',
    ];

    public function editInfo()
    {
        $info = [];
        foreach (self::FIELDS as $field => $key) {
            $info[$field] = Setting::where('key', $key)->first()?->value ?? '';
        }

        return view('admin.contact.index', ['info' => $info]);
    }

    public function updateInfo(Request $request)
    {
        $data = $request->validate([
            'address' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'hours' => 'nullable|string|max:255',
            'map_url' => ['nullable', 'url', 'max:2000', 'regex:/^https?:\/\//i'],
        ]);

        foreach (self::FIELDS as $field => $key) {
            Setting::updateOrCreate(
                ['key' => $key],
                ['value' => $data[$field] ?? '']
            );
        }

        return redirect('/admin/contacto')->with('success', 'Información de contacto actualizada correctamente.');
    }

    public function messages()
    {
        $contacts = Contact::latest()->paginate(20);

        return view('admin.contact.messages', compact('contacts'));
    }

    public function showMessage(Contact $contact)
    {
        if (! $contact->read_at) {
            $contact->update(['read_at' => now()]);
        }
        Audit::log('contact.viewed', Contact::class, $contact->id, 'Mensaje de contacto leído');

        return view('admin.contact.message-show', compact('contact'));
    }

    /**
     * Exporta un mensaje individual a PDF (permiso contact.export).
     *
     * El PDF se genera y se descarga al momento: no se persiste en disco
     * (contiene PII). La auditoría solo registra el ID, nunca el contenido.
     */
    public function exportPdf(Contact $contact)
    {
        Audit::log('contact.exported', Contact::class, $contact->id, "Exportación PDF del mensaje de contacto #{$contact->id}");

        return Pdf::loadView('pdf.contact-message', [
            'contact' => $contact,
            'exportedBy' => auth()->user(),
            'cintillo' => $this->cintilloPng(),
        ])->download('mensaje-contacto-'.$contact->id.'-'.now()->format('Ymd-His').'.pdf');
    }

    /**
     * Compone el cintillo institucional con GD: la franja del navbar
     * (images/banner.png, recortada en "cover" como hace background: center/cover)
     * con el logo (images/logo.png o el del panel) pegado a la izquierda.
     * Se pasa al PDF como data URI (dompdf no necesita leer ficheros).
     */
    private function cintilloPng(): string
    {
        $width = 1000;
        $height = 150;
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));

        $banner = @imagecreatefrompng(public_path('images/banner.png'));
        if ($banner) {
            $sourceWidth = imagesx($banner);
            $sourceHeight = imagesy($banner);
            $scale = max($width / $sourceWidth, $height / $sourceHeight);
            $cropWidth = (int) round($width / $scale);
            $cropHeight = (int) round($height / $scale);
            $cropX = (int) round(($sourceWidth - $cropWidth) / 2);
            $cropY = (int) round(($sourceHeight - $cropHeight) / 2);
            imagecopyresampled($canvas, $banner, 0, 0, $cropX, $cropY, $width, $height, $cropWidth, $cropHeight);
            imagedestroy($banner);
        }

        $logoPath = Setting::where('key', 'site_logo')->first()?->value ?: 'images/logo.png';
        $logo = @imagecreatefrompng(public_path($logoPath));
        if ($logo) {
            $logoHeight = 110;
            $logoWidth = (int) round(imagesx($logo) * $logoHeight / imagesy($logo));
            imagecopyresampled($canvas, $logo, 18, (int) (($height - $logoHeight) / 2), 0, 0, $logoWidth, $logoHeight, imagesx($logo), imagesy($logo));
            imagedestroy($logo);
        }

        ob_start();
        imagepng($canvas);
        $png = (string) ob_get_clean();
        imagedestroy($canvas);

        return 'data:image/png;base64,'.base64_encode($png);
    }

    public function destroyMessage(Contact $contact)
    {
        Audit::log('contact.deleted', Contact::class, $contact->id, 'Mensaje de contacto eliminado');
        $contact->delete();

        return redirect('/admin/contacto/mensajes')->with('success', 'Mensaje eliminado correctamente.');
    }
}
