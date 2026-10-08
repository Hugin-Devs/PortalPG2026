@extends('layouts.public')

@section('title', 'Contacto')

@section('content')
<div class="container my-5">
    <h1 class="text-primary fw-bold mb-4 border-bottom border-primary pb-2">CONTACTO</h1>

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
</div>
@endsection
