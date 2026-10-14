<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Gaceta;
use App\Services\Webp;
use App\Support\Audit;
use App\Support\Uploads;
use Illuminate\Http\Request;

class GacetaController extends Controller
{
    public function index()
    {
        $gacetas = Gaceta::orderBy('fecha', 'desc')
            // numero es NOT NULL y se valida como dígitos (1-6) al crear;
            // CAST ... AS integer es portable (UNSIGNED es exclusivo de MySQL).
            ->orderByRaw('CAST(numero AS integer) DESC, id DESC')
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
        $filename = time().'_'.uniqid().'.'.Uploads::safeExtension($file, ['pdf', 'jpg', 'jpeg', 'png', 'webp']);
        $path = $file->storeAs('gacetas', $filename, 'public');
        Webp::convert($path);

        $gaceta = Gaceta::create([
            'numero' => $request->numero,
            'fecha' => $request->fecha,
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'file_hash' => hash_file('sha256', $file->getRealPath()),
            'user_id' => auth()->id(),
        ]);
        Audit::log('gaceta.created', Gaceta::class, $gaceta->id, "Gaceta cargada: nº {$gaceta->numero}");

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
            $filename = time().'_'.uniqid().'.'.Uploads::safeExtension($file, ['pdf', 'jpg', 'jpeg', 'png', 'webp']);
            $data['path'] = $file->storeAs('gacetas', $filename, 'public');
            Webp::convert($data['path']);
            $data['original_name'] = $file->getClientOriginalName();
            $data['mime_type'] = $file->getMimeType();
            $data['size'] = $file->getSize();
            $data['file_hash'] = hash_file('sha256', $file->getRealPath());
        }

        $gaceta->update($data);
        Audit::log('gaceta.updated', Gaceta::class, $gaceta->id, "Gaceta actualizada: nº {$gaceta->numero}");

        return redirect('/admin/gacetas')->with('success', 'Gaceta actualizada correctamente.');
    }

    public function destroy(Gaceta $gaceta)
    {
        Audit::log('gaceta.deleted', Gaceta::class, $gaceta->id, "Gaceta eliminada: nº {$gaceta->numero}");
        \Storage::disk('public')->delete($gaceta->path);
        Webp::delete($gaceta->path);
        $gaceta->delete();

        return redirect('/admin/gacetas')->with('success', 'Gaceta eliminada correctamente.');
    }
}
