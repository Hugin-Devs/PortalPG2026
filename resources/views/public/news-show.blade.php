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
        <script>
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
                var url = {!! json_encode($shareUrl) !!};
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
