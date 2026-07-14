@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Servicios</h1>
        <a href="{{ url('/admin/servicios/crear') }}" class="btn btn-primary">Nuevo Servicio</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table">
        <thead>
            <tr>
                <th>Título</th>
                <th>Slug</th>
                <th>Orden</th>
                <th>Activo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($services as $service)
            <tr>
                <td>{{ $service->title }}</td>
                <td>{{ $service->slug }}</td>
                <td>{{ $service->order }}</td>
                <td>
                    @if($service->is_active)
                        <span class="badge bg-success">Sí</span>
                    @else
                        <span class="badge bg-danger">No</span>
                    @endif
                </td>
                <td>
                    <a href="{{ url('/admin/servicios/' . $service->id . '/editar') }}" class="btn btn-sm btn-warning">Editar</a>
                    <form action="{{ url('/admin/servicios/' . $service->id) }}" method="POST" style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Eliminar este servicio?')">Eliminar</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $services->links() }}
</div>
@endsection
