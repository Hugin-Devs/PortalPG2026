@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Mensajes de Contacto</h1>
        @if(auth()->user()->hasPermission('contact.edit'))
        <a href="{{ url('/admin/contacto') }}" class="btn btn-outline-primary">Editar información de contacto</a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    @if($contacts->isEmpty())
        <div class="alert alert-info mb-0">No hay mensajes de contacto.</div>
    @else
    <div class="table-responsive">
        <table class="table table-hover align-middle">
            <thead>
                <tr>
                    <th style="width: 90px;">Estado</th>
                    <th>Asunto</th>
                    <th>Nombre</th>
                    <th>Email</th>
                    <th style="width: 160px;">Fecha</th>
                    <th style="width: 90px;"></th>
                </tr>
            </thead>
            <tbody>
                @foreach($contacts as $msg)
                <tr>
                    <td>
                        @if($msg->read_at)
                            <span class="badge bg-secondary">Leído</span>
                        @else
                            <span class="badge bg-success">Nuevo</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ url('/admin/contacto/mensajes/' . $msg->id) }}" class="text-decoration-none {{ $msg->read_at ? '' : 'fw-bold' }}">
                            {{ $msg->subject }}
                        </a>
                    </td>
                    <td>{{ $msg->name }}</td>
                    <td>{{ $msg->email }}</td>
                    <td>{{ $msg->created_at->format('d/m/Y H:i') }}</td>
                    <td>
                        @if(auth()->user()->hasPermission('contact.delete'))
                        <form action="{{ url('/admin/contacto/mensajes/' . $msg->id) }}" method="POST">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="btn btn-sm btn-outline-danger" data-confirm="Eliminar este mensaje?">Eliminar</button>
                        </form>
                        @endif
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-3">
        {{ $contacts->links() }}
    </div>
    @endif
</div>
@endsection
