<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Contact;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index()
    {
        $seo = [
            'title' => 'Contacto',
            'description' => 'Formulario de contacto del Gobierno de Distrito Capital. Envíenos sus consultas, sugerencias o solicitudes.',
            'url' => route('contact.index'),
        ];

        return view('public.contact', compact('seo'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|max:255',
            'email' => 'required|email',
            'subject' => 'required|max:255',
            'message' => 'required',
        ]);

        Contact::create($data);

        return back()->with('success', 'Mensaje enviado correctamente. Nos pondremos en contacto pronto.');
    }
}
