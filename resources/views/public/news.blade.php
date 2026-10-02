@extends('layouts.public')

@section('title', 'Noticias - Portal GDC')

@section('content')
<div class="container my-5">
    <h1 class="text-primary fw-bold mb-4 border-bottom border-primary pb-2">NOTICIAS</h1>

    <div class="row g-4">
        @foreach($posts as $post)
        <div class="col-md-4">
            <div class="card h-100 shadow-sm">
                @if($post->featured_image)
                    <img src="{{ $post->featured_image }}" class="card-img-top" alt="{{ $post->title }}" style="height: 200px; object-fit: cover;">
                @else
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                        <span class="text-muted">Sin imagen</span>
                    </div>
                @endif
                <div class="card-body d-flex flex-column">
                    @if($post->category)
                        <span class="badge bg-primary mb-2 align-self-start">{{ $post->category->name }}</span>
                    @endif
                    <h5 class="card-title text-dark">{{ $post->title }}</h5>
                    <p class="card-text text-secondary flex-grow-1">{{ Str::limit($post->excerpt, 120) }}</p>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">{{ $post->published_at?->format('d/m/Y') ?? $post->created_at->format('d/m/Y') }}</small>
                        <a href="{{ route('news.show', $post->slug) }}" class="text-primary fw-bold text-decoration-none">Leer más →</a>
                    </div>
                </div>
            </div>
        </div>
        @endforeach
    </div>

    <div class="mt-4">
        {{ $posts->links() }}
    </div>
</div>
@endsection
