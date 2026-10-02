@extends('layouts.public')

@section('title', 'Contacto - Portal GDC')

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
            <h4 class="text-primary">Información de contacto</h4>
            <ul class="list-unstyled mt-3">
                <li class="mb-2"><strong>Dirección:</strong> Av. Principal, Distrito Capital</li>
                <li class="mb-2"><strong>Teléfono:</strong> +58 212-XXX-XXXX</li>
                <li class="mb-2"><strong>Email:</strong> contacto@gdc.gob.ve</li>
            </ul>
            <div class="mt-4 p-3 bg-light rounded">
                <h5>Horario de atención</h5>
                <p class="mb-0">Lunes a viernes: 8:00 AM - 4:00 PM</p>
            </div>
            <div class="mt-4">
                <h5>Ubicación</h5>
                <div class="ratio ratio-16x9">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d283.27263825321114!2d-66.91497767711432!3d10.506585216453733!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x8c2a5f4f6e625ba7%3A0x6e9963acb779ffdc!2sEdificio%20Toromaima%20-%20G.D.C.!5e0!3m2!1ses!2sve!4v1674657617845!5m2!1ses!2sve" style="border:0;" allowfullscreen="" loading="lazy" referrerpolicy="no-referrer-when-downgrade"></iframe>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
