@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Sobre Nosotros</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ url('/admin/nosotros') }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label class="fw-bold">Breve Historia</label>
            <textarea name="history" class="form-control" rows="6">{{ old('history', $history) }}</textarea>
        </div>
        <div class="mb-3">
            <label class="fw-bold">Misión</label>
            <textarea name="mission" class="form-control" rows="4">{{ old('mission', $mission) }}</textarea>
        </div>
        <div class="mb-3">
            <label class="fw-bold">Visión</label>
            <textarea name="vision" class="form-control" rows="4">{{ old('vision', $vision) }}</textarea>
        </div>
        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
    </form>
</div>
@endsection
