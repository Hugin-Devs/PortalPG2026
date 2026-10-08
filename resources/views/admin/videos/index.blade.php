@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Gestión de Videos</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <ul class="nav nav-tabs mb-4" id="videosTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="tab-cargar" data-bs-toggle="tab" data-bs-target="#panel-cargar" type="button" role="tab" aria-controls="panel-cargar" aria-selected="true">Cargar videos</button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="tab-portada" data-bs-toggle="tab" data-bs-target="#panel-portada" type="button" role="tab" aria-controls="panel-portada" aria-selected="false">Mostrar en portada</button>
        </li>
    </ul>

    <div class="tab-content" id="videosTabsContent">
        {{-- Pestaña 1: cargar --}}
        <div class="tab-pane fade show active" id="panel-cargar" role="tabpanel" aria-labelledby="tab-cargar" tabindex="0">
            @if(auth()->user()->hasPermission('videos.upload'))
            <div class="card mb-4">
                <div class="card-body">
                    <form action="{{ url('/admin/videos') }}" method="POST" enctype="multipart/form-data">
                        @csrf
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label" for="videoTitle">Título</label>
                                <input type="text" id="videoTitle" name="title" class="form-control" value="{{ old('title') }}" required maxlength="255">
                                @error('title')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-8">
                                <label class="form-label">Origen</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="type" id="typeYoutube" value="youtube" {{ old('type', 'youtube') === 'youtube' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="typeYoutube">YouTube (URL)</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="type" id="typeFile" value="file" {{ old('type') === 'file' ? 'checked' : '' }}>
                                        <label class="form-check-label" for="typeFile">Archivo (subir)</label>
                                    </div>
                                </div>
                                @error('type')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-8" id="fieldYoutube">
                                <label class="form-label" for="videoUrl">URL de YouTube</label>
                                <input type="url" id="videoUrl" name="url" class="form-control" value="{{ old('url') }}" placeholder="https://www.youtube.com/watch?v=..." {{ old('type', 'youtube') === 'youtube' ? '' : 'disabled' }}>
                                @error('url')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-8 d-none" id="fieldFile">
                                <label class="form-label" for="videoFile">Archivo de video</label>
                                <input type="file" id="videoFile" name="file" class="form-control" accept=".mp4,.webm,.ogg" {{ old('type') === 'file' ? '' : 'disabled' }}>
                                <div class="form-text">Formatos: mp4, webm, ogg. Máximo 100 MB.</div>
                                @error('file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                            </div>
                            <div class="col-md-4 d-flex align-items-end">
                                <button type="submit" class="btn btn-primary w-100">Cargar video</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
            @endif

            <div class="row g-3">
                @forelse($videos as $video)
                <div class="col-md-4 col-xl-3">
                    <div class="card h-100 {{ $video->is_featured ? 'border-primary' : '' }}">
                        <div class="bg-dark d-flex align-items-center justify-content-center" style="height: 140px;">
                            @if($video->type === 'youtube' && $video->embed_url)
                                <span class="text-white"><i class="fa-brands fa-youtube fa-2x"></i></span>
                            @else
                                <span class="text-white"><i class="fa-solid fa-film fa-2x"></i></span>
                            @endif
                        </div>
                        <div class="card-body p-2">
                            <small class="d-block text-truncate" title="{{ $video->title }}"><strong>{{ $video->title }}</strong></small>
                            <small class="text-muted d-block">{{ $video->type === 'youtube' ? 'YouTube' : 'Archivo' }}</small>
                            @if($video->is_featured)
                                <span class="badge bg-primary mt-1">Portada</span>
                            @endif
                            @if(auth()->user()->hasPermission('videos.delete'))
                            <form action="{{ url('/admin/videos/' . $video->id) }}" method="POST" style="display:inline">
                                @csrf
                                @method('DELETE')
                                <button type="submit" class="btn btn-sm btn-danger mt-2" onclick="return confirm('Eliminar este video?')">Eliminar</button>
                            </form>
                            @endif
                        </div>
                    </div>
                </div>
                @empty
                <p class="text-muted">No hay videos cargados.</p>
                @endforelse
            </div>
        </div>

        {{-- Pestaña 2: destacado en portada --}}
        <div class="tab-pane fade" id="panel-portada" role="tabpanel" aria-labelledby="tab-portada" tabindex="0">
            @if(auth()->user()->hasPermission('videos.manage'))
            @if($videos->isEmpty())
                <p class="text-muted">No hay videos cargados.</p>
            @else
            <form action="{{ url('/admin/videos/destacar') }}" method="POST">
                @csrf
                <div class="card mb-3">
                    <div class="card-body">
                        @foreach($videos as $video)
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="video_id" id="feat{{ $video->id }}" value="{{ $video->id }}" {{ $video->is_featured ? 'checked' : '' }}>
                            <label class="form-check-label" for="feat{{ $video->id }}">
                                {{ $video->title }}
                                <small class="text-muted">({{ $video->type === 'youtube' ? 'YouTube' : 'Archivo' }})</small>
                            </label>
                        </div>
                        @endforeach
                        <div class="form-check mb-3">
                            <input class="form-check-input" type="radio" name="video_id" id="featNone" value="">
                            <label class="form-check-label" for="featNone">Ninguno (ocultar sección de videos en la portada)</label>
                        </div>
                        @error('video_id')<div class="text-danger small mb-2">{{ $message }}</div>@enderror
                        <button type="submit" class="btn btn-primary">Guardar selección</button>
                    </div>
                </div>
            </form>
            @endif
            @else
            <p class="text-muted">No tienes permiso para gestionar la portada de videos.</p>
            @endif
        </div>
    </div>
</div>
<script>
    (function () {
        var typeYt = document.getElementById('typeYoutube');
        var typeFile = document.getElementById('typeFile');
        var fieldYt = document.getElementById('fieldYoutube');
        var fieldFile = document.getElementById('fieldFile');

        function syncType() {
            var isYoutube = typeYt.checked;
            fieldYt.classList.toggle('d-none', !isYoutube);
            fieldFile.classList.toggle('d-none', isYoutube);
            document.getElementById('videoUrl').disabled = !isYoutube;
            document.getElementById('videoFile').disabled = isYoutube;
        }
        typeYt.addEventListener('change', syncType);
        typeFile.addEventListener('change', syncType);
        syncType();

        var hash = window.location.hash;
        if (hash === '#tab-portada') {
            var el = document.getElementById('tab-portada');
            if (el && !el.classList.contains('active')) {
                new bootstrap.Tab(el).show();
            }
        } else if (hash === '#tab-cargar') {
            var first = document.getElementById('tab-cargar');
            if (first && !first.classList.contains('active')) {
                new bootstrap.Tab(first).show();
            }
        }
    })();
</script>
@endsection
