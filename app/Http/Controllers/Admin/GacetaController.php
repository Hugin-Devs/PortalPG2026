<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gaceta;
use App\Services\Webp;
use Illuminate\Http\Request;

class GacetaController extends Controller
{
    public function index()
    {
        $gacetas = Gaceta::orderBy('fecha', 'desc')
            ->orderByRaw('CAST(numero AS UNSIGNED) DESC, id DESC')
            ->paginate(12);

        return view('admin.gacetas.index', compact('gacetas'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'file' => 'required|mimes:pdf,jpg,jpeg,png,webp|max:20480',
            'numero' => 'required|string|digits_between:1,6',
            'fecha' => 'required|date',
        ]);

        $file = $request->file('file');
        $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('gacetas', $filename, 'public');
        Webp::convert($path);

        Gaceta::create([
            'numero' => $request->numero,
            'fecha' => $request->fecha,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'user_id' => auth()->id(),
        ]);

        return redirect('/admin/gacetas')->with('success', 'Gaceta cargada correctamente.');
    }

    public function edit(Gaceta $gaceta)
    {
        return view('admin.gacetas.edit', compact('gaceta'));
    }

    public function update(Request $request, Gaceta $gaceta)
    {
        $request->validate([
            'file' => 'nullable|mimes:pdf,jpg,jpeg,png,webp|max:20480',
            'numero' => 'required|string|digits_between:1,6',
            'fecha' => 'required|date',
        ]);

        $data = [
            'numero' => $request->numero,
            'fecha' => $request->fecha,
        ];

        if ($request->hasFile('file')) {
            \Storage::disk('public')->delete($gaceta->path);
            Webp::delete($gaceta->path);

            $file = $request->file('file');
            $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
            $data['path'] = $file->storeAs('gacetas', $filename, 'public');
            Webp::convert($data['path']);
            $data['original_name'] = $file->getClientOriginalName();
            $data['mime_type'] = $file->getMimeType();
            $data['size'] = $file->getSize();
        }

        $gaceta->update($data);

        return redirect('/admin/gacetas')->with('success', 'Gaceta actualizada correctamente.');
    }

    public function destroy(Gaceta $gaceta)
    {
        \Storage::disk('public')->delete($gaceta->path);
        Webp::delete($gaceta->path);
        $gaceta->delete();

        return redirect('/admin/gacetas')->with('success', 'Gaceta eliminada correctamente.');
    }
}
