@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Información de Contacto</h1>
    <p class="text-muted">Datos que se muestran en el pie de la sección de contacto (inicio, página de contacto y "Sobre Nosotros").</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ url('/admin/contacto') }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="fw-bold">Dirección</label>
            <input type="text" name="address" class="form-control @error('address') is-invalid @enderror" maxlength="255" value="{{ old('address', $info['address']) }}">
            @error('address') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label class="fw-bold">Teléfono</label>
            <input type="text" name="phone" class="form-control @error('phone') is-invalid @enderror" maxlength="50" value="{{ old('phone', $info['phone']) }}">
            @error('phone') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label class="fw-bold">Correo electrónico</label>
            <input type="email" name="email" class="form-control @error('email') is-invalid @enderror" maxlength="255" value="{{ old('email', $info['email']) }}">
            @error('email') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label class="fw-bold">Horario de atención</label>
            <input type="text" name="hours" class="form-control @error('hours') is-invalid @enderror" maxlength="255" value="{{ old('hours', $info['hours']) }}">
            @error('hours') <div class="invalid-feedback">{{ $message }}</div> @enderror
        </div>
        <div class="mb-3">
            <label class="fw-bold">URL del mapa (Google Maps &lt;embed&gt;)</label>
            <input type="url" name="map_url" class="form-control @error('map_url') is-invalid @enderror" value="{{ old('map_url', $info['map_url']) }}">
            @error('map_url') <div class="invalid-feedback">{{ $message }}</div> @enderror
            <div class="form-text">Pegue la URL completa del código "Insertar un mapa" de Google Maps.</div>
        </div>
        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
    </form>
</div>
@endsection
