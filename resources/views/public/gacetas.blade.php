@extends('layouts.public')

@section('title', 'Gacetas Oficiales - Portal GDC')

@section('content')
<div class="container my-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
            <li class="breadcrumb-item active" aria-current="page">Gacetas Oficiales</li>
        </ol>
    </nav>

    <h1 class="text-primary fw-bold mb-4 border-bottom border-primary pb-2">Gacetas Oficiales</h1>

    @if($gacetas->isEmpty())
        <div class="alert alert-light border">Aún no hay gacetas publicadas.</div>
    @endif

    <div class="row g-4">
        @foreach($gacetas as $gaceta)
        <div class="col-md-4 col-lg-3">
            <a href="{{ asset('storage/' . $gaceta->path) }}" target="_blank" rel="noopener noreferrer" class="text-decoration-none">
                <div class="card h-100 shadow-sm">
                    @if(str_starts_with($gaceta->mime_type, 'image/'))
                        <img src="{{ asset('storage/' . $gaceta->path) }}" class="card-img-top" alt="Gaceta Oficial N° {{ $gaceta->numero }}" loading="lazy" decoding="async" style="height: 200px; object-fit: cover;">
                    @else
                        <div class="bg-light d-flex align-items-center justify-content-center" style="height: 200px;">
                            <i class="fa-solid fa-file-pdf text-danger" style="font-size: 4rem;"></i>
                        </div>
                    @endif
                    <div class="card-body d-flex flex-column">
                        <h5 class="card-title text-dark mb-1">Gaceta Oficial N° {{ $gaceta->numero }}</h5>
                        <small class="text-secondary">{{ $gaceta->fecha->format('d/m/Y') }}</small>
                        <span class="text-primary fw-bold mt-auto pt-2">Ver gaceta <i class="fa-solid fa-arrow-up-right-from-square small"></i></span>
                    </div>
                </div>
            </a>
        </div>
        @endforeach
    </div>

    <div class="mt-4">
        {{ $gacetas->links() }}
    </div>
</div>
@endsection
