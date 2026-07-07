@extends('layouts.public')

@section('title', 'Inicio - Portal GDC')

@section('content')
<div class="container my-5">
    {{-- Hero Slider --}}
    <div id="heroSlider" class="carousel slide mb-5" data-bs-ride="carousel">
        <div class="carousel-inner rounded shadow-sm">
            @foreach($featuredPosts as $index => $post)
            <div class="carousel-item {{ $index == 0 ? 'active' : '' }}">
                <div class="d-flex align-items-center justify-content-center bg-primary text-white" style="height: 400px;">
                    <div class="text-center p-4">
                        <h2 class="display-4 fw-bold">{{ $post->title }}</h2>
                        <p class="lead">{{ $post->excerpt }}</p>
                        <a href="{{ route('news.show', $post->slug) }}" class="btn btn-warning mt-3 px-4">Leer más</a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
        <button class="carousel-control-prev" type="button" data-bs-target="#heroSlider" data-bs-slide="prev">
            <span class="carousel-control-prev-icon"></span>
        </button>
        <button class="carousel-control-next" type="button" data-bs-target="#heroSlider" data-bs-slide="next">
            <span class="carousel-control-next-icon"></span>
        </button>
    </div>

    {{-- Categorías Destacadas --}}
    <section class="mb-5">
        <h3 class="border-bottom border-danger pb-2 mb-4 text-primary fw-bold">INFÓRMATE Y COMUNICA</h3>
        <div class="row g-4">
            @foreach($categories as $category)
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm hover-shadow transition">
                    <div class="card-body text-center p-4">
                        <h5 class="card-title text-primary">{{ $category->name }}</h5>
                        <a href="{{ route('news.category', $category->slug) }}" class="btn btn-outline-danger mt-3">Ver más</a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </section>

    {{-- Grilla de Noticias --}}
    <section>
        <h3 class="border-bottom border-danger pb-2 mb-4 text-primary fw-bold">ÚLTIMAS NOTICIAS</h3>
        <div class="row g-4">
            @foreach($latestPosts as $post)
            <div class="col-md-4">
                <div class="card h-100 shadow-sm">
                    @if($post->featured_image)
                        <img src="{{ $post->featured_image }}" class="card-img-top" alt="...">
                    @else
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                            <span class="text-muted">Sin imagen</span>
                        </div>
                    @endif
                    <div class="card-body">
                        <h5 class="card-title text-dark">{{ $post->title }}</h5>
                        <p class="card-text text-secondary">{{ Str::limit($post->excerpt, 100) }}</p>
                        <a href="{{ route('news.show', $post->slug) }}" class="text-danger fw-bold text-decoration-none">Leer más →</a>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    </section>
</div>
@endsection