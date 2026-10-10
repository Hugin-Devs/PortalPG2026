@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Noticias</h1>
        <div>
            @if(auth()->user()->hasPermission('news.approve'))
            <button type="submit" form="bulkPublishForm" class="btn btn-success" data-confirm="¿Publicar las noticias seleccionadas?">Publicar seleccionadas</button>
            @endif
            @if(auth()->user()->hasPermission('news.create'))
            <a href="{{ url('/admin/noticias/crear') }}" class="btn btn-primary">Nueva Noticia</a>
            @endif
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('warning'))
        <div class="alert alert-warning">{{ session('warning') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    @if(auth()->user()->hasPermission('news.approve'))
    <form id="bulkPublishForm" action="{{ url('/admin/noticias/publicar') }}" method="POST">
        @csrf
    </form>
    @endif

    <table class="table">
        <thead>
            <tr>
                @if(auth()->user()->hasPermission('news.approve'))
                <th style="width:30px"><input type="checkbox" id="selectAll" form="bulkPublishForm" class="form-check-input" title="Seleccionar todo"></th>
                @endif
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
                @if(auth()->user()->hasPermission('news.approve'))
                <td><input type="checkbox" name="posts[]" value="{{ $post->id }}" form="bulkPublishForm" class="form-check-input post-checkbox"></td>
                @endif
                <td>{{ $post->title }}</td>
                <td>{{ $post->category->name ?? 'Sin categoría' }}</td>
                <td>
                    @if($post->status == 'draft')
                        <span class="badge bg-warning text-dark">Pendiente de aprobación</span>
                    @elseif($post->published_at && $post->published_at->isFuture())
                        <span class="badge bg-info text-dark">Programada · {{ $post->published_at->format('d/m/Y H:i') }}</span>
                    @else
                        <span class="badge bg-success">Publicado</span>
                    @endif
                </td>
                <td>{{ $post->created_at->format('d/m/Y') }}</td>
                <td>
                    @if(auth()->user()->hasPermission('news.approve') && $post->status == 'draft')
                    <form action="{{ url('/admin/noticias/' . $post->id . '/aprobar') }}" method="POST" style="display:inline">
                        @csrf
                        <button type="submit" class="btn btn-sm btn-success" data-confirm="¿Publicar esta noticia ahora?">Aprobar</button>
                    </form>
                    @endif
                    @if(auth()->user()->hasPermission('news.edit'))
                    <a href="{{ url('/admin/noticias/' . $post->id . '/editar') }}" class="btn btn-sm btn-warning">Editar</a>
                    @endif
                    @if(auth()->user()->hasPermission('news.delete'))
                    <form action="{{ url('/admin/noticias/' . $post->id) }}" method="POST" style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="Eliminar esta noticia?">Eliminar</button>
                    </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $posts->links() }}
</div>

<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    (function () {
        var selectAll = document.getElementById('selectAll');
        if (selectAll) {
            selectAll.addEventListener('change', function () {
                document.querySelectorAll('.post-checkbox').forEach(function (cb) {
                    cb.checked = selectAll.checked;
                });
            });
        }
    })();
</script>
@endsection
