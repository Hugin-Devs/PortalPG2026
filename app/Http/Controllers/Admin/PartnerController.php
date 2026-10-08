<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Partner;
use App\Services\Webp;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

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
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'url' => 'nullable|url',
            'order' => 'nullable|integer|min:0',
        ]);

        $data['slug'] = $this->uniqueSlug($request->name);

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
            'description' => 'nullable|string',
            'logo' => 'nullable|image|mimes:jpeg,png,jpg,gif,svg,webp|max:2048',
            'url' => 'nullable|url',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
            'remove_logo' => 'nullable|boolean',
        ]);

        $data['is_active'] = $request->has('is_active');
        unset($data['remove_logo']);

        if ($partner->slug !== Str::slug($request->name)) {
            $data['slug'] = $this->uniqueSlug($request->name, $partner->id);
        }

        if ($request->hasFile('logo')) {
            $old = $partner->logo;
            $data['logo'] = $this->storeLogo($request->file('logo'));
            if ($old) {
                \Storage::disk('public')->delete($old);
                Webp::delete($old);
            }
        } elseif ($request->boolean('remove_logo')) {
            if ($partner->logo) {
                \Storage::disk('public')->delete($partner->logo);
                Webp::delete($partner->logo);
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
            Webp::delete($partner->logo);
        }
        $partner->delete();

        return redirect('/admin/entes')->with('success', 'Ente afiliado eliminado correctamente.');
    }

    private function storeLogo($file): string
    {
        $filename = time().'_'.uniqid().'.'.$file->getClientOriginalExtension();
        $path = $file->storeAs('partners', $filename, 'public');
        Webp::convert($path);

        return $path;
    }

    private function uniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base = Str::slug($name) ?: 'ente';
        $slug = $base;
        $i = 2;

        while (
            Partner::where('slug', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }
}
