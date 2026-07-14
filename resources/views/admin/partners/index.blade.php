@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Entes Afiliados</h1>
        <a href="{{ url('/admin/entes/crear') }}" class="btn btn-primary">Nuevo Ente</a>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Logo</th>
                <th>URL</th>
                <th>Orden</th>
                <th>Activo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($partners as $partner)
            <tr>
                <td>{{ $partner->name }}</td>
                <td>
                    @if($partner->logo)
                        <img src="{{ asset('storage/' . $partner->logo) }}" alt="{{ $partner->name }}" height="30">
                    @else
                        -
                    @endif
                </td>
                <td>{{ $partner->url ?? '-' }}</td>
                <td>{{ $partner->order }}</td>
                <td>
                    @if($partner->is_active)
                        <span class="badge bg-success">Sí</span>
                    @else
                        <span class="badge bg-danger">No</span>
                    @endif
                </td>
                <td>
                    <a href="{{ url('/admin/entes/' . $partner->id . '/editar') }}" class="btn btn-sm btn-warning">Editar</a>
                    <form action="{{ url('/admin/entes/' . $partner->id) }}" method="POST" style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Eliminar este ente?')">Eliminar</button>
                    </form>
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $partners->links() }}
</div>
@endsection
