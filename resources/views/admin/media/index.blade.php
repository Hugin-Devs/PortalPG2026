@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Gestión de Medios</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card mb-4">
        <div class="card-body">
            <form action="{{ url('/admin/medios') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-md-8">
                        <label>Subir archivo</label>
                        <input type="file" name="file" class="form-control" required>
                    </div>
                    <div class="col-md-4">
                        <button type="submit" class="btn btn-primary w-100">Subir</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="row g-3">
        @foreach($media as $item)
        <div class="col-md-3">
            <div class="card">
                @if(str_starts_with($item->mime_type, 'image/'))
                    <img src="{{ asset('storage/' . $item->path) }}" class="card-img-top" alt="{{ $item->original_name }}" style="height: 150px; object-fit: cover;">
                @else
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                        <span class="text-muted">{{ $item->mime_type }}</span>
                    </div>
                @endif
                <div class="card-body p-2">
                    <small class="text-muted d-block text-truncate" title="{{ $item->original_name }}">{{ $item->original_name }}</small>
                    <form action="{{ url('/admin/medios/' . $item->id) }}" method="POST">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger mt-1" onclick="return confirm('Eliminar este archivo?')">Eliminar</button>
                    </form>
                </div>
            </div>
        </div>
        @endforeach
    </div>
    <div class="mt-3">
        {{ $media->links() }}
    </div>
</div>
@endsection
