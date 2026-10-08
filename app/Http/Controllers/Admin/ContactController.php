<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use App\Models\Setting;
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
            'map_url' => 'nullable|url|max:2000',
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

        return view('admin.contact.message-show', compact('contact'));
    }

    public function destroyMessage(Contact $contact)
    {
        $contact->delete();

        return redirect('/admin/contacto/mensajes')->with('success', 'Mensaje eliminado correctamente.');
    }
}
