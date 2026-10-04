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
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'url' => 'nullable|url',
            'order' => 'nullable|integer|min:0',
        ]);

        if ($request->hasFile('logo')) {
            $data['logo'] = $this->storeLogo($request->file('logo'));
        } else {
            unset($data['logo']);
        }

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
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'url' => 'nullable|url',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'remove_logo' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->has('is_active');
        unset($data['remove_logo']);

        if ($request->hasFile('logo')) {
            $old = $partner->logo;
            $data['logo'] = $this->storeLogo($request->file('logo'));
            if ($old) {
                \Storage::disk('public')->delete($old);
            }
        } elseif ($request->boolean('remove_logo')) {
            if ($partner->logo) {
                \Storage::disk('public')->delete($partner->logo);
            }
            $data['logo'] = null;
        } else {
            unset($data['logo']);
        }

        $partner->update($data);
        return redirect('/admin/entes')->with('success', 'Ente afiliado actualizado correctamente.');
    }

    public function destroy(Partner $partner)
    {
        if ($partner->logo) {
            \Storage::disk('public')->delete($partner->logo);
        }
        $partner->delete();
        return redirect('/admin/entes')->with('success', 'Ente afiliado eliminado correctamente.');
    }

    private function storeLogo($file): string
    {
        $filename = time() . '_' . uniqid() . '.' . $file->getClientOriginalExtension();
        return $file->storeAs('partners', $filename, 'public');
    }
}
