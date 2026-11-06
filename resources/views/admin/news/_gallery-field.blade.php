@php
    $existing = (isset($post) && $post) ? $post->postMedia()->with('video')->get() : collect();
    $linkedVideoIds = $existing->where('type', 'video')->pluck('video_id')->all();
    $availableVideos = \App\Models\Video::latest()->whereNotIn('id', $linkedVideoIds)->get();

    $galleryVideoMax = \App\Http\Controllers\Admin\NewsController::GALLERY_VIDEO_MAX;
    // Videos ya enlazados que ocupan cupo (los que se marquen "Quitar" lo liberan
    // en cliente; el servidor sigue validando el tope real).
    $attachedVideoCount = $existing->where('type', 'video')->count();
    // Repoblación tras un error de validación: ids elegidos en el selector modal.
    $oldAttachIds = collect((array) old('attach_video', []))
        ->map(fn ($id) => (int) $id)
        ->filter(fn ($id) => $availableVideos->contains('id', $id))
        ->values();
    $freeVideoSlots = max(0, $galleryVideoMax - $attachedVideoCount - $oldAttachIds->count());
@endphp

<div class="mb-3 border rounded p-3">
    <label class="fw-bold">Galería y videos (carrusel interno de la noticia)</label>
    <div class="form-text mb-2">La portada es el primer ítem del carrusel. Máximo {{ \App\Http\Controllers\Admin\NewsController::GALLERY_IMAGE_MAX }} imágenes y {{ $galleryVideoMax }} videos por noticia. Los videos se guardan también en el módulo <a href="{{ url('/admin/videos') }}">Videos</a>.</div>

    @if($existing->isNotEmpty())
        <div class="row g-2 mb-3" data-testid="gallery-items">
            @foreach($existing as $media)
            <div class="col-3">
                <div class="border rounded p-1 text-center h-100">
                    @if($media->type === 'image')
                        <img src="{{ asset('storage/' . $media->path) }}" alt="Galería {{ $loop->iteration }}" class="img-fluid mb-1 rounded" style="height: 70px; object-fit: cover;">
                    @else
                        <div class="bg-dark text-white rounded d-flex align-items-center justify-content-center mb-1" style="height: 70px;">
                            <i class="fa-solid fa-video"></i>
                        </div>
                        <div class="small text-truncate" title="{{ $media->video?->title }}">{{ $media->video?->title }}</div>
                        <span class="badge bg-secondary">{{ $media->video?->type === 'youtube' ? 'YouTube' : 'Archivo' }}</span>
                    @endif
                    <div class="d-flex justify-content-center gap-1 mt-1">
                        <button type="submit" formmethod="post"
                                formaction="{{ url('/admin/noticias/' . $post->id . '/media/' . $media->id . '/move') }}?direction=up"
                                class="btn btn-outline-secondary btn-sm px-2" title="Subir" {{ $loop->first ? 'disabled' : '' }}>↑</button>
                        <button type="submit" formmethod="post"
                                formaction="{{ url('/admin/noticias/' . $post->id . '/media/' . $media->id . '/move') }}?direction=down"
                                class="btn btn-outline-secondary btn-sm px-2" title="Bajar" {{ $loop->last ? 'disabled' : '' }}>↓</button>
                    </div>
                    <div class="form-check mt-1">
                        <input type="checkbox" name="remove_media[]" value="{{ $media->id }}" class="form-check-input"
                               data-media-type="{{ $media->type }}" id="rmMedia{{ $media->id }}">
                        <label class="form-check-label small" for="rmMedia{{ $media->id }}">Quitar</label>
                    </div>
                </div>
            </div>
            @endforeach
        </div>
    @endif

    <div class="mb-2">
        <label class="form-label">Agregar imágenes</label>
        <input type="file" name="gallery_images[]" multiple class="form-control" accept="image/png,image/jpeg,image/gif,image/webp">
        @error('gallery_images') <div class="text-danger">{{ $message }}</div> @enderror
        @error('gallery_images.*') <div class="text-danger">{{ $message }}</div> @enderror
    </div>

    <div class="mb-2">
        <label class="form-label d-block">Agregar videos existentes del módulo</label>
        @if($availableVideos->isEmpty())
            <p class="text-muted small mb-0">
                No hay otros videos disponibles en el módulo. Súbelos desde <a href="{{ url('/admin/videos') }}">Videos</a>.
            </p>
        @else
            <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#videoLibraryModal">
                <i class="fa-solid fa-photo-film me-1"></i>Elegir videos de la biblioteca
            </button>
            <span class="small text-muted ms-2" id="videoSlotCounter"
                  data-max="{{ $galleryVideoMax }}"
                  data-attached="{{ $attachedVideoCount }}">Cupos disponibles: {{ $freeVideoSlots }} de {{ $galleryVideoMax }}</span>

            <div id="videoAttachChips" class="d-flex flex-wrap gap-2 mt-2">
                @foreach($oldAttachIds as $oldVideoId)
                    @php $oldVideo = $availableVideos->firstWhere('id', $oldVideoId); @endphp
                    @if($oldVideo)
                        <span class="badge bg-light text-dark border d-inline-flex align-items-center gap-2 py-2 video-chip" data-id="{{ $oldVideo->id }}">
                            @if($oldVideo->type === 'youtube')
                                <i class="fa-brands fa-youtube text-danger"></i>
                            @else
                                <i class="fa-solid fa-film text-secondary"></i>
                            @endif
                            <span class="text-truncate" style="max-width: 220px;">{{ $oldVideo->title }}</span>
                            <button type="button" class="btn-close video-chip-remove" aria-label="Quitar"></button>
                            <input type="hidden" name="attach_video[]" value="{{ $oldVideo->id }}">
                        </span>
                    @endif
                @endforeach
            </div>
        @endif
        @error('attach_video') <div class="text-danger">{{ $message }}</div> @enderror
        @error('attach_video.*') <div class="text-danger">{{ $message }}</div> @enderror
    </div>

    <div class="mb-2">
        <label class="form-label">Cargar video nuevo — URL de YouTube (una por línea)</label>
        <textarea name="new_video_urls" class="form-control" rows="2" placeholder="https://www.youtube.com/watch?v=...">{{ old('new_video_urls') }}</textarea>
        <div class="form-text">Formatos admitidos: watch?v=, youtu.be/, shorts/, embed/.</div>
        @error('new_video_urls') <div class="text-danger">{{ $message }}</div> @enderror
    </div>

    <div class="mb-0">
        <label class="form-label">Cargar video nuevo — archivo</label>
        <input type="file" name="new_video_files[]" multiple class="form-control" accept=".mp4,.webm,.ogg">
        <div class="form-text">Formatos: mp4, webm, ogg. Máx. 100 MB por archivo.</div>
        @error('new_video_files') <div class="text-danger">{{ $message }}</div> @enderror
        @error('new_video_files.*') <div class="text-danger">{{ $message }}</div> @enderror
    </div>
</div>

@if($availableVideos->isNotEmpty())
<div class="modal fade" id="videoLibraryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Biblioteca de videos</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                <input type="search" id="videoLibraryFilter" class="form-control mb-3"
                       placeholder="Filtrar por título…" aria-label="Filtrar videos por título">
                <div class="row g-2" id="videoLibraryGrid">
                    @foreach($availableVideos as $video)
                    <div class="col-6 col-md-4 video-pick-col">
                        <button type="button" class="video-pick w-100 h-100 p-2 text-start bg-white border rounded position-relative"
                                data-id="{{ $video->id }}" data-title="{{ $video->title }}" data-type="{{ $video->type }}" aria-pressed="false">
                            <span class="video-pick-check position-absolute top-0 end-0 m-1 badge bg-primary d-none"><i class="fa-solid fa-check"></i></span>
                            <span class="d-flex align-items-center gap-2 mb-1">
                                @if($video->type === 'youtube')
                                    <i class="fa-brands fa-youtube fa-lg text-danger"></i>
                                @else
                                    <i class="fa-solid fa-film fa-lg text-secondary"></i>
                                @endif
                                <span class="badge bg-secondary">{{ $video->type === 'youtube' ? 'YouTube' : 'Archivo' }}</span>
                            </span>
                            <span class="d-block small text-truncate" title="{{ $video->title }}">{{ $video->title }}</span>
                        </button>
                    </div>
                    @endforeach
                </div>
                <p class="text-muted small mb-0 d-none" id="videoLibraryEmpty">Ningún video coincide con el filtro.</p>
            </div>
            <div class="modal-footer">
                <a href="{{ url('/admin/videos') }}" class="btn btn-outline-secondary btn-sm">Gestionar en Videos</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ request()->attributes->get('csp_nonce') }}">
document.addEventListener('DOMContentLoaded', function () {
    const modal = document.getElementById('videoLibraryModal');
    const chips = document.getElementById('videoAttachChips');
    const counter = document.getElementById('videoSlotCounter');
    if (!modal || !chips || !counter) return;

    const max = parseInt(counter.dataset.max, 10) || 0;
    const baseAttached = parseInt(counter.dataset.attached, 10) || 0;
    const filter = document.getElementById('videoLibraryFilter');
    const empty = document.getElementById('videoLibraryEmpty');
    // Checkboxes "Quitar" de los videos ya enlazados: marcarlos libera cupo.
    const removeVideoChecks = document.querySelectorAll('input[name="remove_media[]"][data-media-type="video"]');

    function selectedIds() {
        return Array.from(chips.querySelectorAll('input[name="attach_video[]"]')).map(function (input) {
            return String(input.value);
        });
    }

    function attachedNow() {
        let removed = 0;
        removeVideoChecks.forEach(function (check) {
            if (check.checked) removed++;
        });
        return Math.max(0, baseAttached - removed);
    }

    function refresh() {
        const selected = selectedIds();
        const free = Math.max(0, max - attachedNow() - selected.length);
        counter.textContent = 'Cupos disponibles: ' + free + ' de ' + max;

        modal.querySelectorAll('.video-pick').forEach(function (card) {
            const on = selected.indexOf(String(card.dataset.id)) !== -1;
            card.classList.toggle('border-primary', on);
            card.classList.toggle('opacity-50', !on && free === 0);
            card.classList.toggle('pe-none', !on && free === 0);
            const check = card.querySelector('.video-pick-check');
            if (check) check.classList.toggle('d-none', !on);
            card.setAttribute('aria-pressed', on ? 'true' : 'false');
        });
    }

    function addChip(id, title, type) {
        if (chips.querySelector('.video-chip[data-id="' + id + '"]')) return;

        const chip = document.createElement('span');
        chip.className = 'badge bg-light text-dark border d-inline-flex align-items-center gap-2 py-2 video-chip';
        chip.dataset.id = id;

        const icon = document.createElement('i');
        icon.className = type === 'youtube' ? 'fa-brands fa-youtube text-danger' : 'fa-solid fa-film text-secondary';
        chip.appendChild(icon);

        const label = document.createElement('span');
        label.className = 'text-truncate';
        label.style.maxWidth = '220px';
        label.textContent = title;
        chip.appendChild(label);

        const remove = document.createElement('button');
        remove.type = 'button';
        remove.className = 'btn-close video-chip-remove';
        remove.setAttribute('aria-label', 'Quitar');
        chip.appendChild(remove);

        const hidden = document.createElement('input');
        hidden.type = 'hidden';
        hidden.name = 'attach_video[]';
        hidden.value = id;
        chip.appendChild(hidden);

        chips.appendChild(chip);
    }

    modal.querySelectorAll('.video-pick').forEach(function (card) {
        card.addEventListener('click', function () {
            const id = String(card.dataset.id);
            const chip = chips.querySelector('.video-chip[data-id="' + id + '"]');
            if (chip) {
                chip.remove();
            } else if (selectedIds().length < max - attachedNow()) {
                addChip(id, card.dataset.title, card.dataset.type);
            }
            refresh();
        });
    });

    chips.addEventListener('click', function (event) {
        const button = event.target.closest('.video-chip-remove');
        if (!button) return;
        const chip = button.closest('.video-chip');
        if (chip) chip.remove();
        refresh();
    });

    removeVideoChecks.forEach(function (check) {
        check.addEventListener('change', refresh);
    });

    function normalize(value) {
        return String(value).toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    if (filter) {
        filter.addEventListener('input', function () {
            const query = normalize(filter.value.trim());
            let visible = 0;
            modal.querySelectorAll('.video-pick-col').forEach(function (col) {
                const card = col.querySelector('.video-pick');
                const title = card ? normalize(card.dataset.title) : '';
                const show = !query || title.indexOf(query) !== -1;
                col.classList.toggle('d-none', !show);
                if (show) visible++;
            });
            if (empty) empty.classList.toggle('d-none', visible > 0);
        });
    }

    // Refleja selecciones repobladas tras un error de validación (old()).
    refresh();
});
</script>
@endif
