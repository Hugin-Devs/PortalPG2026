@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Gacetas Oficiales</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if(auth()->user()->hasPermission('gacetas.create'))
    <div class="card mb-4">
        <div class="card-body">
            <h5 class="card-title">Nueva Gaceta</h5>
            <form action="{{ url('/admin/gacetas') }}" method="POST" enctype="multipart/form-data">
                @csrf
                <div class="row g-3 align-items-end">
                    <div class="col-md-5">
                        <label class="form-label">Archivo (PDF o imagen)</label>
                        <input type="file" name="file" class="form-control" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                        @error('file')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">N° de gaceta</label>
                        <input type="text" name="numero" class="form-control" inputmode="numeric" pattern="[0-9]{1,6}" maxlength="6" placeholder="045" value="{{ old('numero') }}" required>
                        @error('numero')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Fecha</label>
                        <input type="date" name="fecha" class="form-control" value="{{ old('fecha') }}" required>
                        @error('fecha')<div class="text-danger small mt-1">{{ $message }}</div>@enderror
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Cargar</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    @endif

    @if($gacetas->isEmpty())
        <div class="alert alert-light border">Aún no hay gacetas cargadas.</div>
    @endif

    <div class="row g-3">
        @foreach($gacetas as $gaceta)
        <div class="col-md-3">
            <div class="card h-100">
                @if(str_starts_with($gaceta->mime_type, 'image/'))
                    <img src="{{ asset('storage/' . $gaceta->path) }}" class="card-img-top" alt="Gaceta N° {{ $gaceta->numero }}" loading="lazy" decoding="async" style="height: 150px; object-fit: cover;">
                @else
                    <div class="bg-light d-flex align-items-center justify-content-center" style="height: 150px;">
                        <i class="fa-solid fa-file-pdf text-danger" style="font-size: 3rem;"></i>
                    </div>
                @endif
                <div class="card-body p-2">
                    <small class="d-block fw-bold">Gaceta Oficial N° {{ $gaceta->numero }}</small>
                    <small class="text-muted d-block mb-2">{{ $gaceta->fecha->format('d/m/Y') }}</small>
                    <a href="{{ asset('storage/' . $gaceta->path) }}" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-primary">Ver</a>
                    @if(auth()->user()->hasPermission('gacetas.edit'))
                        <a href="{{ url('/admin/gacetas/' . $gaceta->id . '/editar') }}" class="btn btn-sm btn-warning">Editar</a>
                    @endif
                    @if(auth()->user()->hasPermission('gacetas.delete'))
                    <form action="{{ url('/admin/gacetas/' . $gaceta->id) }}" method="POST" style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Eliminar esta gaceta?')">Eliminar</button>
                    </form>
                    @endif
                </div>
            </div>
        </div>
        @endforeach
    </div>
    <div class="mt-3">
        {{ $gacetas->links() }}
    </div>
</div>
@endsection
