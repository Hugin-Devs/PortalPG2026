<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\OrgMember;
use Illuminate\Http\Request;

class OrgMemberController extends Controller
{
    public function index()
    {
        $members = OrgMember::orderBy('order')->paginate(10);

        return view('admin.org-members.index', compact('members'));
    }

    public function create()
    {
        return view('admin.org-members.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|max:255',
            'position' => 'required|max:255',
            'photo' => $this->photoRule(),
            'description' => 'nullable',
            'order' => 'nullable|integer|min:0',
        ]);

        OrgMember::create($data);

        return redirect('/admin/estructura')->with('success', 'Miembro creado correctamente.');
    }

    public function edit(OrgMember $orgMember)
    {
        return view('admin.org-members.edit', compact('orgMember'));
    }

    public function update(Request $request, OrgMember $orgMember)
    {
        $data = $request->validate([
            'name' => 'required|max:255',
            'position' => 'required|max:255',
            'photo' => $this->photoRule(),
            'description' => 'nullable',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->has('is_active');

        $orgMember->update($data);

        return redirect('/admin/estructura')->with('success', 'Miembro actualizado correctamente.');
    }

    public function destroy(OrgMember $orgMember)
    {
        $orgMember->delete();

        return redirect('/admin/estructura')->with('success', 'Miembro eliminado correctamente.');
    }

    /**
     * Ruta relativa segura: bajo `images/` o `storage/`, solo caracteres
     * alfanuméricos y `- _ /` (sin puntos intermedios, evita dobles extensiones)
     * y con extensión de imagen permitida.
     */
    private function photoRule(): array
    {
        return [
            'nullable',
            'string',
            'max:255',
            'regex:/^(images|storage)\/[A-Za-z0-9_\-\/]+\.(jpg|jpeg|png|webp)$/i',
        ];
    }
}
