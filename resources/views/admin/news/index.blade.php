@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Noticias</h1>
        @if(auth()->user()->hasPermission('news.create'))
        <a href="{{ url('/admin/noticias/crear') }}" class="btn btn-primary">Nueva Noticia</a>
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
                <th>Título</th>
                <th>Categoría</th>
                <th>Estado</th>
                <th>Fecha</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($posts as $post)
            <tr>
                <td>{{ $post->title }}</td>
                <td>{{ $post->category->name ?? 'Sin categoría' }}</td>
                <td>
                    @if($post->status == 'published')
                        <span class="badge bg-success">Publicado</span>
                    @else
                        <span class="badge bg-secondary">Borrador</span>
                    @endif
                </td>
                <td>{{ $post->created_at->format('d/m/Y') }}</td>
                <td>
                    @if(auth()->user()->hasPermission('news.edit'))
                    <a href="{{ url('/admin/noticias/' . $post->id . '/editar') }}" class="btn btn-sm btn-warning">Editar</a>
                    @endif
                    @if(auth()->user()->hasPermission('news.delete'))
                    <form action="{{ url('/admin/noticias/' . $post->id) }}" method="POST" style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Eliminar esta noticia?')">Eliminar</button>
                    </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $posts->links() }}
</div>
@endsection
