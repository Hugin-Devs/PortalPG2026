@extends('layouts.public')

@section('title', 'Sobre Nosotros - Portal GDC')

@section('content')
<div class="container my-5">
    <h1 class="text-primary fw-bold mb-5 border-bottom border-primary pb-2 text-center">GOBIERNO DEL DISTRITO CAPITAL</h1>

    <div class="row g-4 mb-5">
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0 text-center p-4">
                <h4 class="text-primary fw-bold">Breve Historia</h4>
                <p class="text-secondary">{{ $history ?: 'Información institucional...' }}</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0 text-center p-4">
                <h4 class="text-primary fw-bold">Misión</h4>
                <p class="text-secondary">{{ $mission ?: 'Nuestra misión institucional...' }}</p>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card h-100 shadow-sm border-0 text-center p-4">
                <h4 class="text-primary fw-bold">Visión</h4>
                <p class="text-secondary">{{ $vision ?: 'Hacia dónde vamos...' }}</p>
            </div>
        </div>
    </div>

    <section class="mt-5">
        <h3 class="text-primary fw-bold mb-4 text-center border-bottom border-primary pb-2">ESTRUCTURA ORGANIZATIVA</h3>
        <div id="orgCarousel" class="carousel slide" data-bs-ride="carousel">
            <div class="carousel-inner">
                @forelse($members->chunk(3) as $chunkIndex => $chunk)
                <div class="carousel-item {{ $chunkIndex == 0 ? 'active' : '' }}">
                    <div class="row justify-content-center">
                        @foreach($chunk as $member)
                        <div class="col-md-4 mb-3">
                            <div class="card shadow-sm h-100">
                                <img src="{{ $member->photo ? asset($member->photo) : asset('images/user-placeholder.png') }}" class="card-img-top" alt="{{ $member->name }}" loading="lazy" decoding="async" style="height: 200px; object-fit: cover;">
                                <div class="card-body text-center">
                                    <h5 class="card-title">{{ $member->name }}</h5>
                                    <h6 class="text-primary">{{ $member->position }}</h6>
                                    <p class="card-text small">{{ $member->description }}</p>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </div>
                @empty
                <div class="carousel-item active">
                    <div class="text-center text-muted py-5">
                        <p>No hay miembros registrados en la estructura organizativa.</p>
                    </div>
                </div>
                @endforelse
            </div>
            @if($members->count() > 0)
            <button class="carousel-control-prev" type="button" data-bs-target="#orgCarousel" data-bs-slide="prev">
                <span class="carousel-control-prev-icon bg-dark rounded"></span>
            </button>
            <button class="carousel-control-next" type="button" data-bs-target="#orgCarousel" data-bs-slide="next">
                <span class="carousel-control-next-icon bg-dark rounded"></span>
            </button>
            @endif
        </div>
    </section>

    <section class="my-5 bg-white p-5 rounded shadow-sm">
        <h3 class="text-primary fw-bold mb-4 border-bottom border-primary pb-2">CONTACTO</h3>
        @if(session('success'))
            <div class="alert alert-success">{{ session('success') }}</div>
        @endif
        <div class="row g-5">
            <div class="col-md-6">
                <form method="POST" action="{{ route('contact.store') }}">
                    @csrf
                    <div class="mb-3">
                        <label class="form-label">Nombre completo</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Correo electrónico</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Asunto</label>
                        <input type="text" name="subject" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Mensaje</label>
                        <textarea name="message" class="form-control" rows="5" required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">Enviar mensaje</button>
                </form>
            </div>
            <div class="col-md-5 offset-md-1">
                @include('public._contact-info')
            </div>
        </div>
    </section>
</div>
@endsection
