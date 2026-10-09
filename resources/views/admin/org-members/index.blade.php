@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Estructura Organizativa</h1>
        @if(auth()->user()->hasPermission('org.create'))
        <a href="{{ url('/admin/estructura/crear') }}" class="btn btn-primary">Nuevo Miembro</a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <table class="table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Cargo</th>
                <th>Orden</th>
                <th>Activo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($members as $member)
            <tr>
                <td>{{ $member->name }}</td>
                <td>{{ $member->position }}</td>
                <td>{{ $member->order }}</td>
                <td>
                    @if($member->is_active)
                        <span class="badge bg-success">Sí</span>
                    @else
                        <span class="badge bg-danger">No</span>
                    @endif
                </td>
                <td>
                    @if(auth()->user()->hasPermission('org.edit'))
                    <a href="{{ url('/admin/estructura/' . $member->id . '/editar') }}" class="btn btn-sm btn-warning">Editar</a>
                    @endif
                    @if(auth()->user()->hasPermission('org.delete'))
                    <form action="{{ url('/admin/estructura/' . $member->id) }}" method="POST" style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="Eliminar este miembro?">Eliminar</button>
                    </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $members->links() }}
</div>
@endsection
