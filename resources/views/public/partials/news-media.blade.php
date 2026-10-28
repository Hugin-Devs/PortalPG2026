{{--
    Bloque de medios del detalle de noticia (destacada + galería).

    Compartido por el layout clásico (todo el ancho, caja fija de 560 px) y el
    layout "revista" (medio a la derecha con la proporción real del primer
    elemento) que se activa cuando todos los medios son verticales.

    Requiere: $post, $galleryItems, $mediaAspect, $modoRevista.
--}}
@php
    $carouselCount = ($post->featured_image ? 1 : 0) + $galleryItems->count();

    // Modo revista: la caja la dimensiona el contenedor (aspect-ratio del
    // primer medio) y los ítems la llenan; el max-height evita que un 9:16
    // dispare una caja más alta que la pantalla.
    $cajaRevista = $modoRevista && $mediaAspect
        ? 'aspect-ratio: '.$mediaAspect.'; max-height: min(78vh, 760px); margin-inline: auto; background-color: #fff;'
        : '';
    $rellenoItem = $modoRevista ? 'h-100' : '';
    $estiloVideo = $modoRevista
        ? 'object-fit: contain;'
        : 'height: 560px; object-fit: contain;';
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
    <div id="newsGallery" class="carousel slide rounded mb-4" data-bs-interval="false" style="{{ $cajaRevista }}">
        <div class="carousel-inner {{ $rellenoItem }}">
            @if($post->featured_image)
                <div class="carousel-item active {{ $rellenoItem }}">
                    <div class="d-flex align-items-center justify-content-center bg-white {{ $rellenoItem }}" @unless($modoRevista) style="height: 560px;" @endunless>
                        <x-img path="storage/{{ $post->featured_image }}" alt="{{ $post->title }}" class="d-block" style="max-height: 100%; max-width: 100%; width: auto; height: auto;" />
                    </div>
                </div>
            @endif
            @foreach($galleryItems as $index => $media)
                <div class="carousel-item {{ (! $post->featured_image && $index === 0) ? 'active' : '' }} {{ $rellenoItem }}">
                    @if($media->type === 'image')
                        <div class="d-flex align-items-center justify-content-center bg-white {{ $rellenoItem }}" @unless($modoRevista) style="height: 560px;" @endunless>
                            <x-img path="storage/{{ $media->path }}" alt="{{ $post->title }}" class="d-block" style="max-height: 100%; max-width: 100%; width: auto; height: auto;" decoding="async" />
                        </div>
                    @elseif($media->video && $media->video->type === 'youtube' && $media->video->embed_url)
                        <div class="bg-dark d-flex align-items-center justify-content-center {{ $rellenoItem }}" @unless($modoRevista) style="height: 560px;" @endunless>
                            <iframe src="{{ $media->video->embed_url }}" title="{{ $media->video->title }}" class="w-100 h-100" frameborder="0" allowfullscreen loading="lazy"></iframe>
                        </div>
                    @elseif($media->video?->source_url)
                        <video src="{{ $media->video->source_url }}" controls class="d-block w-100 {{ $rellenoItem }} bg-dark" style="{{ $estiloVideo }}" title="{{ $media->video->title }}" preload="metadata"></video>
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
    <div class="d-flex align-items-center justify-content-center bg-white rounded mb-4" style="{{ $cajaRevista ?: 'height: 560px;' }}">
        <x-img path="storage/{{ $post->featured_image }}" alt="{{ $post->title }}" class="d-block" style="max-height: 100%; max-width: 100%; width: auto; height: auto;" />
    </div>
@elseif($galleryItems->isNotEmpty())
    @php $media = $galleryItems->first(); @endphp
    <div class="rounded mb-4 overflow-hidden" style="{{ $cajaRevista ?: 'height: 560px;' }}">
        @if($media->type === 'image')
            <div class="d-flex align-items-center justify-content-center bg-white h-100">
                <x-img path="storage/{{ $media->path }}" alt="{{ $post->title }}" class="d-block" style="max-height: 100%; max-width: 100%; width: auto; height: auto;" decoding="async" />
            </div>
        @elseif($media->video && $media->video->type === 'youtube' && $media->video->embed_url)
            <div class="bg-dark d-flex align-items-center justify-content-center h-100">
                <iframe src="{{ $media->video->embed_url }}" title="{{ $media->video->title }}" class="w-100 h-100" frameborder="0" allowfullscreen loading="lazy"></iframe>
            </div>
        @elseif($media->video?->source_url)
            <video src="{{ $media->video->source_url }}" controls class="d-block w-100 h-100 bg-dark" style="{{ $estiloVideo }}" title="{{ $media->video->title }}" preload="metadata"></video>
        @endif
    </div>
@endif
