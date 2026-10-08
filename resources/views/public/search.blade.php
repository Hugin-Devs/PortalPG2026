@extends('layouts.public')

@section('title', 'Buscar: ' . $q)

@section('content')
<div class="container my-5">
    <h1 class="text-primary fw-bold mb-4 border-bottom border-primary pb-2">RESULTADOS DE BÚSQUEDA</h1>

    <p class="text-muted mb-4">
        @if($posts->total() > 0)
            Se encontraron {{ $posts->total() }} resultado(s) para "<strong>{{ $q }}</strong>"
        @else
            No se encontraron resultados para "<strong>{{ $q }}</strong>"
        @endif
    </p>

    <div class="row g-4">
        @foreach($posts as $post)
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                <div class="card-body d-flex flex-column">
                    @if($post->category)
                        <span class="badge bg-primary mb-2 align-self-start">{{ $post->category->name }}</span>
                    @endif
                    <h5 class="card-title">{{ $post->title }}</h5>
                    <p class="card-text text-secondary flex-grow-1">{{ Str::limit($post->excerpt, 120) }}</p>
                    <a href="{{ route('news.show', $post->slug) }}" class="text-primary fw-bold text-decoration-none">Leer más →</a>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-4">
        {{ $posts->withQueryString()->links() }}
    </div>
</div>
@endsection
