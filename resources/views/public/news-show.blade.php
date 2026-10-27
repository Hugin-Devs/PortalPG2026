@extends('layouts.public')

@section('title', $post->title)

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

        @php
            $shareUrl = route('news.show', $post->slug);
            $shareTitle = rawurlencode($post->title);
            $shareText = rawurlencode($post->title . ' ' . $shareUrl);
        @endphp
        <div class="d-flex flex-wrap align-items-center gap-2 mb-4" data-testid="share-bar">
            <span class="text-muted small fw-bold me-1">COMPARTIR:</span>
            <a class="btn btn-outline-primary btn-sm" href="https://www.facebook.com/sharer/sharer.php?u={{ rawurlencode($shareUrl) }}" target="_blank" rel="noopener noreferrer" aria-label="Compartir en Facebook"><i class="fa-brands fa-facebook-f"></i></a>
            <a class="btn btn-outline-primary btn-sm" href="https://twitter.com/intent/tweet?url={{ rawurlencode($shareUrl) }}&text={{ $shareTitle }}" target="_blank" rel="noopener noreferrer" aria-label="Compartir en X"><i class="fa-brands fa-x-twitter"></i></a>
            <a class="btn btn-outline-primary btn-sm" href="https://wa.me/?text={{ $shareText }}" target="_blank" rel="noopener noreferrer" aria-label="Compartir en WhatsApp"><i class="fa-brands fa-whatsapp"></i></a>
            <a class="btn btn-outline-primary btn-sm" href="https://t.me/share/url?url={{ rawurlencode($shareUrl) }}&text={{ $shareTitle }}" target="_blank" rel="noopener noreferrer" aria-label="Compartir en Telegram"><i class="fa-brands fa-telegram"></i></a>
            <button type="button" class="btn btn-outline-secondary btn-sm" id="copyLinkBtn" aria-label="Copiar enlace"><i class="fa-solid fa-link me-1"></i><span id="copyLinkText">Copiar enlace</span></button>
        </div>
        <script nonce="{{ request()->attributes->get('csp_nonce') }}">
            function copyLinkFeedback(ok) {
                var label = document.getElementById('copyLinkText');
                var previous = label.textContent;
                label.textContent = ok ? '¡Copiado!' : 'No se pudo copiar';
                setTimeout(function () { label.textContent = previous; }, 2000);
            }
            function copyLinkFallback(text) {
                var ta = document.createElement('textarea');
                ta.value = text;
                ta.setAttribute('readonly', '');
                ta.style.position = 'fixed';
                ta.style.opacity = '0';
                document.body.appendChild(ta);
                ta.select();
                var ok = false;
                try { ok = document.execCommand('copy'); } catch (e) { ok = false; }
                document.body.removeChild(ta);
                return ok;
            }
            document.getElementById('copyLinkBtn').addEventListener('click', function () {
                var url = {!! json_encode($shareUrl, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) !!};
                if (navigator.clipboard && window.isSecureContext) {
                    navigator.clipboard.writeText(url).then(function () {
                        copyLinkFeedback(true);
                    }).catch(function () {
                        copyLinkFeedback(copyLinkFallback(url));
                    });
                } else {
                    copyLinkFeedback(copyLinkFallback(url));
                }
            });
        </script>

        @php
            $galleryItems = $post->postMedia->sortBy('position')->values();
            $carouselCount = ($post->featured_image ? 1 : 0) + $galleryItems->count();
        @endphp

        @if($carouselCount > 1)
            <style nonce="{{ request()->attributes->get('csp_nonce') }}">
                #newsGallery .carousel-control-prev-icon,
                #newsGallery .carousel-control-next-icon {
                    filter: none;
                }
                #newsGallery .carousel-control-prev-icon {
                    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23333'%3e%3cpath d='M11.354 1.646a.5.5 0 0 1 0 .708L5.707 8l5.647 5.646a.5.5 0 0 1-.708.708l-6-6a.5.5 0 0 1 0-.708l6-6a.5.5 0 0 1 .708 0z'/%3e%3c/svg%3e");
                }
                #newsGallery .carousel-control-next-icon {
                    background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 16 16' fill='%23333'%3e%3cpath d='M4.646 1.646a.5.5 0 0 1 .708 0l6 6a.5.5 0 0 1 0 .708l-6 6a.5.5 0 0 1-.708-.708L10.293 8 4.646 2.354a.5.5 0 0 1 0-.708z'/%3e%3c/svg%3e");
                }
            </style>
            <div id="newsGallery" class="carousel slide rounded mb-4" data-bs-interval="false">
                <div class="carousel-inner">
                    @if($post->featured_image)
                        <div class="carousel-item active">
                            <div class="d-flex align-items-center justify-content-center bg-white" style="height: 560px;">
                                <x-img path="storage/{{ $post->featured_image }}" alt="{{ $post->title }}" class="d-block" style="max-height: 100%; max-width: 100%; width: auto; height: auto;" />
                            </div>
                        </div>
                    @endif
                    @foreach($galleryItems as $media)
                        <div class="carousel-item">
                            @if($media->type === 'image')
                                <div class="d-flex align-items-center justify-content-center bg-white" style="height: 560px;">
                                    <x-img path="storage/{{ $media->path }}" alt="{{ $post->title }}" class="d-block" style="max-height: 100%; max-width: 100%; width: auto; height: auto;" decoding="async" />
                                </div>
                            @elseif($media->video && $media->video->type === 'youtube' && $media->video->embed_url)
                                <div class="bg-dark d-flex align-items-center justify-content-center" style="height: 560px;">
                                    <iframe src="{{ $media->video->embed_url }}" title="{{ $media->video->title }}" class="w-100 h-100" frameborder="0" allowfullscreen loading="lazy"></iframe>
                                </div>
                            @elseif($media->video?->source_url)
                                <video src="{{ $media->video->source_url }}" controls class="d-block w-100 bg-dark" style="height: 560px; object-fit: contain;" title="{{ $media->video->title }}" preload="metadata"></video>
                            @endif
                        </div>
                    @endforeach
                </div>
                <button class="carousel-control-prev" type="button" data-bs-target="#newsGallery" data-bs-slide="prev" aria-label="Imagen anterior">
                    <span class="carousel-control-prev-icon"></span>
                </button>
                <button class="carousel-control-next" type="button" data-bs-target="#newsGallery" data-bs-slide="next" aria-label="Imagen siguiente">
                    <span class="carousel-control-next-icon"></span>
                </button>
            </div>
        @elseif($post->featured_image)
            <div class="d-flex align-items-center justify-content-center bg-white rounded mb-4" style="height: 560px;">
                <x-img path="storage/{{ $post->featured_image }}" alt="{{ $post->title }}" class="d-block" style="max-height: 100%; max-width: 100%; width: auto; height: auto;" />
            </div>
        @endif

        <div class="lead mb-4 fw-semibold">{{ $post->excerpt }}</div>

        <div class="content fs-5 lh-lg">
            {!! $post->rendered_content !!}
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
