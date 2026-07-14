<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ServiceController extends Controller
{
    public function index()
    {
        $services = Service::orderBy('order')->paginate(10);
        return view('admin.services.index', compact('services'));
    }

    public function create()
    {
        return view('admin.services.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'title' => 'required|max:255',
            'description' => 'nullable',
            'content' => 'nullable',
            'icon' => 'nullable|max:255',
            'external_url' => 'nullable|url',
            'order' => 'nullable|integer|min:0',
        ]);

        $data['slug'] = Str::slug($request->title);

        Service::create($data);
        return redirect('/admin/servicios')->with('success', 'Servicio creado correctamente.');
    }

    public function edit(Service $service)
    {
        return view('admin.services.edit', compact('service'));
    }

    public function update(Request $request, Service $service)
    {
        $data = $request->validate([
            'title' => 'required|max:255',
            'description' => 'nullable',
            'content' => 'nullable',
            'icon' => 'nullable|max:255',
            'external_url' => 'nullable|url',
            'order' => 'nullable|integer|min:0',
            'is_active' => 'nullable|boolean',
        ]);

        $data['slug'] = Str::slug($request->title);
        $data['is_active'] = $request->has('is_active');

        $service->update($data);
        return redirect('/admin/servicios')->with('success', 'Servicio actualizado correctamente.');
    }

    public function destroy(Service $service)
    {
        $service->delete();
        return redirect('/admin/servicios')->with('success', 'Servicio eliminado correctamente.');
    }
}
