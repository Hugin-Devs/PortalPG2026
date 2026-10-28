@extends('layouts.public')

@section('title', $category->name)

@section('content')
<div class="container my-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
            <li class="breadcrumb-item"><a href="{{ route('news.index') }}">Noticias</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $category->name }}</li>
        </ol>
    </nav>

    <h1 class="text-primary fw-bold mb-4 border-bottom border-primary pb-2">{{ $category->name }}</h1>

    @if($category->description)
        <p class="lead mb-4">{{ $category->description }}</p>
    @endif

    @include('public._category-pills', ['activeSlug' => $category->slug])

    <div class="row g-4">
        @include('public._category-sidebar', ['activeSlug' => $category->slug])
        <div class="col-lg-9">
            <div class="row g-4">
                @foreach($posts as $post)
                <div class="col-md-4">
                    <div class="card h-100 shadow-sm">
                        @if($post->featured_image)
                            <x-img path="storage/{{ $post->featured_image }}" class="card-img-top" alt="{{ $post->title }}" loading="lazy" decoding="async" style="height: 200px; object-fit: cover;" />
                        @else
                            @php $videoMedia = $post->postMedia->where('type', 'video')->first(); @endphp
                            @if($videoMedia && $videoMedia->video && $videoMedia->video->type === 'file' && $videoMedia->video->source_url)
                                <video src="{{ $videoMedia->video->source_url }}" class="card-img-top w-100 bg-dark" style="height: 200px; object-fit: cover;" preload="metadata" muted></video>
                            @else
                                <div class="bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                                    <span class="text-muted">Sin imagen</span>
                                </div>
                            @endif
                        @endif
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title">{{ $post->title }}</h5>
                            <p class="card-text text-secondary flex-grow-1">{{ Str::limit($post->excerpt, 100) }}</p>
                            <a href="{{ route('news.show', $post->slug) }}" class="text-primary fw-bold text-decoration-none">Leer más →</a>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>
            <div class="mt-4">
                {{ $posts->links() }}
            </div>
        </div>
    </div>
</div>
@endsection
