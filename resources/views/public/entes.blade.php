@extends('layouts.public')

@section('title', 'Entes - Portal GDC')

@section('content')
<div class="container my-5">
    <h1 class="text-primary fw-bold mb-4 border-bottom border-primary pb-2">ENTES</h1>

    @if($entes->isEmpty())
        <p class="text-secondary">Próximamente publicaremos los entes afiliados.</p>
    @else
    <div class="row g-4">
        @foreach($entes as $ente)
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card h-100 border-0 shadow-sm text-center p-3">
                @if($ente->logo)
                    <div class="mb-3">
                        <img src="{{ asset('storage/' . $ente->logo) }}" alt="{{ $ente->name }}" height="70" class="mx-auto">
                    </div>
                @endif
                <h5 class="card-title text-primary">{{ $ente->name }}</h5>
                @if($ente->description)
                    <p class="card-text text-secondary small">{{ Str::limit($ente->description, 100) }}</p>
                @endif
                @if($ente->url)
                    <a href="{{ $ente->url }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm mt-auto">Ir al sitio →</a>
                @else
                    <a href="{{ route('entes.show', $ente->slug) }}" class="btn btn-outline-primary btn-sm mt-auto">Ver más →</a>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    @endif
</div>
@endsection
