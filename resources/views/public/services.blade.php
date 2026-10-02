@extends('layouts.public')

@section('title', 'Entes - Portal GDC')

@section('content')
<div class="container my-5">
    <h1 class="text-primary fw-bold mb-4 border-bottom border-primary pb-2">ENTES</h1>

    <div class="row g-4">
        @foreach($services as $service)
        <div class="col-lg-3 col-md-4 col-sm-6">
            <div class="card h-100 border-0 shadow-sm text-center p-3">
                @if($service->icon)
                    <div class="mb-3">
                        <i class="{{ $service->icon }} fa-3x text-primary"></i>
                    </div>
                @endif
                <h5 class="card-title text-primary">{{ $service->title }}</h5>
                <p class="card-text text-secondary small">{{ Str::limit($service->description, 100) }}</p>
                @if($service->external_url)
                    <a href="{{ $service->external_url }}" target="_blank" class="btn btn-outline-primary btn-sm mt-auto">Ir al sitio →</a>
                @else
                    <a href="{{ route('services.show', $service->slug) }}" class="btn btn-outline-primary btn-sm mt-auto">Ver más →</a>
                @endif
            </div>
        </div>
        @endforeach
    </div>
</div>
@endsection
