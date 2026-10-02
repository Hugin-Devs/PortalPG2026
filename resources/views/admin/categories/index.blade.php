@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Categorías</h1>
        @if(auth()->user()->hasPermission('categories.create'))
        <a href="{{ url('/admin/categorias/crear') }}" class="btn btn-primary">Nueva Categoría</a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <table class="table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Slug</th>
                <th>Padre</th>
                <th>Orden</th>
                <th>Activo</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($categories as $category)
            <tr>
                <td>{{ $category->name }}</td>
                <td>{{ $category->slug }}</td>
                <td>{{ $category->parent->name ?? '-' }}</td>
                <td>{{ $category->order }}</td>
                <td>
                    @if($category->is_active)
                        <span class="badge bg-success">Sí</span>
                    @else
                        <span class="badge bg-danger">No</span>
                    @endif
                </td>
                <td>
                    @if(auth()->user()->hasPermission('categories.edit'))
                    <a href="{{ url('/admin/categorias/' . $category->id . '/editar') }}" class="btn btn-sm btn-warning">Editar</a>
                    @endif
                    @if(auth()->user()->hasPermission('categories.delete'))
                    <form action="{{ url('/admin/categorias/' . $category->id) }}" method="POST" style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Eliminar esta categoría?')">Eliminar</button>
                    </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $categories->links() }}
</div>
@endsection
