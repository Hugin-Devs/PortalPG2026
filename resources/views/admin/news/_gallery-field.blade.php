@php
    $existing = (isset($post) && $post) ? $post->postMedia()->with('video')->get() : collect();
    $linkedVideoIds = $existing->where('type', 'video')->pluck('video_id')->all();
    $availableVideos = \App\Models\Video::latest()->whereNotIn('id', $linkedVideoIds)->get();
@endphp

<div class="mb-3 border rounded p-3">
    <label class="fw-bold">Galería y videos (carrusel interno de la noticia)</label>
    <div class="form-text mb-2">La portada es el primer ítem del carrusel. Máximo {{ \App\Http\Controllers\Admin\NewsController::GALLERY_IMAGE_MAX }} imágenes y {{ \App\Http\Controllers\Admin\NewsController::GALLERY_VIDEO_MAX }} videos por noticia. Los videos se guardan también en el módulo <a href="{{ url('/admin/videos') }}">Videos</a>.</div>

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
                        <input type="checkbox" name="remove_media[]" value="{{ $media->id }}" class="form-check-input" id="rmMedia{{ $media->id }}">
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

    @if($availableVideos->isNotEmpty())
    <div class="mb-2">
        <label class="form-label">Agregar videos existentes del módulo</label>
        @foreach($availableVideos as $video)
        <div class="form-check">
            <input type="checkbox" name="attach_video[]" value="{{ $video->id }}" class="form-check-input" id="attachV{{ $video->id }}">
            <label class="form-check-label" for="attachV{{ $video->id }}">
                {{ $video->title }} <span class="badge bg-secondary">{{ $video->type === 'youtube' ? 'YouTube' : 'Archivo' }}</span>
            </label>
        </div>
        @endforeach
        @error('attach_video') <div class="text-danger">{{ $message }}</div> @enderror
        @error('attach_video.*') <div class="text-danger">{{ $message }}</div> @enderror
    </div>
    @endif

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
