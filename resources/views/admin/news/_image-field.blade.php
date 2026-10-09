@php
    $currentImage = (isset($post) && $post) ? $post->featured_image : null;
    $initialImage = old('featured_image') ?: $currentImage;
@endphp
<div class="mb-3">
    <label>Imagen destacada</label>

    <div id="featuredImagePreview" class="mb-2" style="{{ $initialImage ? '' : 'display:none;' }}">
        <img id="featuredImageImg" src="{{ $initialImage ? asset('storage/' . $initialImage) : '' }}" alt="Imagen destacada" height="80" class="bg-white rounded p-1 border">
    </div>

    <input type="hidden" name="featured_image" id="featuredImageInput" value="{{ old('featured_image') }}">

    <div class="input-group mb-2">
        <input type="file" name="featured_image_file" id="featuredImageFile" class="form-control" accept="image/png,image/jpeg,image/gif,image/svg+xml,image/webp">
        <button type="button" class="btn btn-outline-primary" data-bs-toggle="modal" data-bs-target="#mediaLibraryModal">Elegir de la biblioteca</button>
    </div>
    <div class="form-text">JPG, PNG, GIF, SVG o WebP · máx. 10 MB — si la imagen ya está en Medios, usa "Elegir de la biblioteca"</div>
    @error('featured_image_file') <div class="text-danger">{{ $message }}</div> @enderror
    @error('featured_image') <div class="text-danger">{{ $message }}</div> @enderror

    @if($currentImage)
    <div class="form-check mt-2">
        <input type="checkbox" name="remove_image" value="1" class="form-check-input" id="removeImage">
        <label class="form-check-label" for="removeImage">Quitar imagen actual</label>
    </div>
    @endif
</div>

<div class="modal fade" id="mediaLibraryModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Biblioteca de imágenes</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Cerrar"></button>
            </div>
            <div class="modal-body">
                @php
                    $libraryImages = \App\Models\Media::where('mime_type', 'like', 'image/%')->latest()->take(24)->get();
                @endphp
                @if($libraryImages->isEmpty())
                    <p class="text-muted mb-0">No hay imágenes en la biblioteca. Súbelas desde <a href="{{ url('/admin/medios') }}">Medios</a>.</p>
                @else
                    <div class="row g-2">
                        @foreach($libraryImages as $media)
                            <div class="col-3">
                                <a href="#" class="media-pick d-block border rounded p-1 text-center" data-path="{{ $media->path }}">
                                    <img src="{{ asset('storage/' . $media->path) }}" alt="{{ $media->original_name }}" class="img-fluid" style="height: 80px; object-fit: cover;">
                                </a>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <a href="{{ url('/admin/medios') }}" class="btn btn-outline-secondary btn-sm">Gestionar en Medios</a>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<script nonce="{{ request()->attributes->get('csp_nonce') }}">
document.addEventListener('DOMContentLoaded', function () {
    const hidden = document.getElementById('featuredImageInput');
    const file = document.getElementById('featuredImageFile');
    const previewWrap = document.getElementById('featuredImagePreview');
    const previewImg = document.getElementById('featuredImageImg');
    const removeChk = document.getElementById('removeImage');
    const modal = document.getElementById('mediaLibraryModal');

    function showPreview(src) {
        previewImg.src = src;
        previewWrap.style.display = '';
    }

    document.querySelectorAll('.media-pick').forEach(function (el) {
        el.addEventListener('click', function (e) {
            e.preventDefault();
            hidden.value = el.dataset.path;
            file.value = '';
            showPreview(el.querySelector('img').src);
            if (removeChk) removeChk.checked = false;
            bootstrap.Modal.getOrCreateInstance(modal).hide();
        });
    });

    file.addEventListener('change', function () {
        if (file.files && file.files[0]) {
            hidden.value = '';
            showPreview(URL.createObjectURL(file.files[0]));
            if (removeChk) removeChk.checked = false;
        }
    });

    if (removeChk) {
        removeChk.addEventListener('change', function () {
            if (removeChk.checked) {
                previewWrap.style.display = 'none';
                hidden.value = '';
                file.value = '';
            }
        });
    }
});
</script>
