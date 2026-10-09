@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Mensaje de Contacto</h1>
        <div>
            @if(auth()->user()->hasPermission('contact.delete'))
            <form action="{{ url('/admin/contacto/mensajes/' . $contact->id) }}" method="POST" class="d-inline">
                @csrf
                @method('DELETE')
                <button type="submit" class="btn btn-outline-danger" data-confirm="Eliminar este mensaje?">Eliminar</button>
            </form>
            @endif
            <a href="{{ url('/admin/contacto/mensajes') }}" class="btn btn-outline-secondary">Volver</a>
        </div>
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-start mb-3">
                <div>
                    <h4 class="mb-1">{{ $contact->subject }}</h4>
                    <span class="text-muted">De <strong>{{ $contact->name }}</strong> &lt;{{ $contact->email }}&gt; · {{ $contact->created_at->format('d/m/Y H:i') }}</span>
                </div>
                @if($contact->read_at)
                    <span class="badge bg-secondary">Leído {{ $contact->read_at->format('d/m/Y H:i') }}</span>
                @else
                    <span class="badge bg-success">Nuevo</span>
                @endif
            </div>
            <hr>
            <div style="white-space: pre-wrap;">{{ $contact->message }}</div>
        </div>
    </div>
</div>
@endsection
