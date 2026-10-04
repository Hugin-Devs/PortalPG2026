@extends('layouts.public')

@section('title', $post->title . ' - Portal GDC')

@section('content')
<div class="container my-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
            <li class="breadcrumb-item"><a href="{{ route('news.index') }}">Noticias</a></li>
            @if($post->category)
                <li class="breadcrumb-item"><a href="{{ route('news.category', $post->category->slug) }}">{{ $post->category->name }}</a></li>
            @endif
            <li class="breadcrumb-item active" aria-current="page">{{ Str::limit($post->title, 40) }}</li>
        </ol>
    </nav>

    <article>
        <h1 class="text-primary fw-bold mb-3">{{ $post->title }}</h1>

        <div class="text-muted mb-4">
            <span>{{ $post->published_at?->format('d/m/Y') ?? $post->created_at->format('d/m/Y') }}</span>
            @if($post->category)
                <span class="ms-3">Categoría: <a href="{{ route('news.category', $post->category->slug) }}" class="text-primary">{{ $post->category->name }}</a></span>
            @endif
            <span class="ms-3">{{ $post->views }} vistas</span>
        </div>

        @if($post->featured_image)
            <img src="{{ asset('storage/' . $post->featured_image) }}" alt="{{ $post->title }}" class="img-fluid rounded mb-4 w-100" style="max-height: 400px; object-fit: cover;">
        @endif

        <div class="lead mb-4 fw-semibold">{{ $post->excerpt }}</div>

        <div class="content fs-5 lh-lg">
            {!! nl2br(e($post->content)) !!}
        </div>
    </article>

    @if($related->isNotEmpty())
    <hr class="my-5">
    <section>
        <h3 class="text-primary fw-bold mb-4">NOTICIAS RELACIONADAS</h3>
        <div class="row g-4">
            @foreach($related as $rel)
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">{{ $rel->title }}</h5>
                        <p class="card-text text-secondary">{{ Str::limit($rel->excerpt, 80) }}</p>
                        <a href="{{ route('news.show', $rel->slug) }}" class="text-primary fw-bold text-decoration-none">Leer más →</a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </section>
    @endif
</div>
@endsection
