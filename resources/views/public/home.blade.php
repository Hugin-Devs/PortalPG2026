@extends('layouts.public')

@section('title', 'Inicio')

@section('content')
<div class="container my-5">
    {{-- Hero Slider --}}
    <div id="heroSlider" class="carousel slide mb-5" data-bs-ride="carousel">
        <div class="carousel-inner rounded shadow-sm">
            @forelse($featuredPosts as $index => $post)
            <div class="carousel-item {{ $index == 0 ? 'active' : '' }}">
                @if($post->featured_image)
                <div class="position-relative" style="height: 400px; background-color: #273376;">
                    <x-img path="storage/{{ $post->featured_image }}" alt="{{ $post->title }}" class="position-absolute top-0 start-0 w-100 h-100" style="object-fit: cover;" />
                    <div class="position-absolute top-0 start-0 w-100 h-100" style="background-color: rgba(15, 23, 42, 0.55);"></div>
                    <div class="position-relative h-100 d-flex align-items-center justify-content-center text-white">
                        <div class="text-center p-4">
                            <h2 class="display-4 fw-bold">{{ $post->title }}</h2>
                            <p class="lead">{{ $post->excerpt }}</p>
                            <a href="{{ route('news.show', $post->slug) }}" class="btn btn-warning mt-3 px-4">Leer más</a>
                        </div>
                    </div>
                </div>
                @else
                <div class="d-flex align-items-center justify-content-center bg-primary text-white" style="height: 400px;">
                    <div class="text-center p-4">
                        <h2 class="display-4 fw-bold">{{ $post->title }}</h2>
                        <p class="lead">{{ $post->excerpt }}</p>
                        <a href="{{ route('news.show', $post->slug) }}" class="btn btn-warning mt-3 px-4">Leer más</a>
                    </div>
                </div>
                @endif
            </div>
            @empty
            <div class="carousel-item active">
                <div class="d-flex align-items-center justify-content-center bg-primary text-white" style="height: 400px;">
                    <div class="text-center p-4">
                        <h2 class="display-4 fw-bold">Bienvenidos al Portal GDC</h2>
                        <p class="lead">Gobierno de Distrito Capital</p>
                    </div>
                </div>
            </div>
            @endforelse
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
        <h3 class="border-bottom border-primary pb-2 mb-4 text-primary fw-bold">INFÓRMATE Y COMUNICA</h3>
        <div class="row g-4">
            @forelse($categories as $category)
            <div class="col-md-4">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body text-center p-4">
                        @if($category->icon)
                            <div class="mb-3"><i class="{{ $category->icon }} fa-3x text-primary"></i></div>
                        @endif
                        <h5 class="card-title text-primary">{{ $category->name }}</h5>
                        @if($category->description)
                            <p class="card-text text-secondary small">{{ Str::limit($category->description, 80) }}</p>
                        @endif
                        <a href="{{ route('news.category', $category->slug) }}" class="btn btn-outline-primary mt-3">Ver más</a>
                    </div>
                </div>
            </div>
            @empty
            <div class="col-12 text-center text-muted">No hay categorías disponibles</div>
            @endforelse
        </div>
    </section>

    {{-- Videos institucionales (portada) --}}
    @if(isset($videos) && $videos->isNotEmpty())
    <section class="mb-5">
        <h3 class="border-bottom border-primary pb-2 mb-4 text-primary fw-bold">VIDEOS INSTITUCIONALES</h3>
        @foreach($videos as $video)
        <div class="ratio ratio-16x9">
            @if($video->type === 'youtube' && $video->embed_url)
                <iframe src="{{ $video->embed_url }}" title="{{ $video->title }}" allowfullscreen></iframe>
            @elseif($video->source_url)
                <video src="{{ $video->source_url }}" controls class="w-100 h-100 bg-dark" style="object-fit: contain;" title="{{ $video->title }}"></video>
            @endif
        </div>
        @endforeach
    </section>
    @endif

    {{-- Grilla de Noticias + Sidebar --}}
    <section>
        <div class="row">
            <div class="col-lg-8">
                <h3 class="border-bottom border-primary pb-2 mb-4 text-primary fw-bold">ÚLTIMAS NOTICIAS</h3>
                <div class="row g-4">
                    @forelse($latestPosts as $post)
                    <div class="col-md-6">
                        <div class="card h-100 shadow-sm">
                            @if($post->featured_image)
                                <x-img path="storage/{{ $post->featured_image }}" class="card-img-top" alt="{{ $post->title }}" loading="lazy" decoding="async" style="height: 200px; object-fit: cover;" />
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
                                <p class="card-text text-secondary flex-grow-1">{{ Str::limit($post->excerpt, 100) }}</p>
                                <a href="{{ route('news.show', $post->slug) }}" class="text-primary fw-bold text-decoration-none">Leer más →</a>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="col-12 text-center text-muted">No hay noticias publicadas</div>
                    @endforelse
                </div>
            </div>
            <div class="col-lg-4">
                {{-- Sidebar: Lo más leído --}}
                <div class="card shadow-sm">
                    <div class="card-header bg-primary text-white fw-bold">LO MÁS LEÍDO</div>
                    <div class="card-body">
                        @forelse($mostRead as $mr)
                        <div class="mb-3 pb-2 border-bottom">
                            <a href="{{ route('news.show', $mr->slug) }}" class="text-decoration-none text-dark">
                                <h6 class="mb-1">{{ $mr->title }}</h6>
                                <small class="text-muted">{{ $mr->views }} vistas · {{ $mr->published_at?->format('d/m/Y') ?? $mr->created_at->format('d/m/Y') }}</small>
                            </a>
                        </div>
                        @empty
                        <p class="text-muted small mb-0">No hay datos disponibles</p>
                        @endforelse
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- Contacto Section --}}
    <section class="my-5 bg-white p-5 rounded shadow-sm">
        <h3 class="text-primary fw-bold mb-4 border-bottom border-primary pb-2">CONTACTO</h3>
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        <div class="row g-5">
            <div class="col-md-6">
                <form method="POST" action="{{ route('contact.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Nombre completo</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Asunto</label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mensaje</label>
                        <textarea name="message" class="form-control" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Enviar mensaje</button>
                </form>
            </div>
            <div class="col-md-5 offset-md-1">
                @include('public._contact-info')
            </div>
        </div>
    </section>
</div>
@endsection
