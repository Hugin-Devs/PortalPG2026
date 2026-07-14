<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use Illuminate\Http\Request;

class PartnerController extends Controller
{
    public function index()
    {
        $partners = Partner::orderBy('order')->paginate(10);
        return view('admin.partners.index', compact('partners'));
    }

    public function create()
    {
        return view('admin.partners.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|max:255',
            'logo' => 'nullable|max:255',
            'url' => 'nullable|url',
            'order' => 'nullable|integer|min:0',
        ]);

        Partner::create($data);
        return redirect('/admin/entes')->with('success', 'Ente afiliado creado correctamente.');
    }

    public function edit(Partner $partner)
    {
        return view('admin.partners.edit', compact('partner'));
    }

    public function update(Request $request, Partner $partner)
    {
        $data = $request->validate([
            'name' => 'required|max:255',
            'logo' => 'nullable|max:255',
            'url' => 'nullable|url',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->has('is_active');

        $partner->update($data);
        return redirect('/admin/entes')->with('success', 'Ente afiliado actualizado correctamente.');
    }

    public function destroy(Partner $partner)
    {
        $partner->delete();
        return redirect('/admin/entes')->with('success', 'Ente afiliado eliminado correctamente.');
    }
}
