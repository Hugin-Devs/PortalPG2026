@extends('layouts.admin')

@section('content')
<div class="container">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h1>Usuarios</h1>
        @if(auth()->user()->hasPermission('users.create'))
        <a href="{{ url('/admin/usuarios/crear') }}" class="btn btn-primary">Nuevo Usuario</a>
        @endif
    </div>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif
    @if(session('error'))
        <div class="alert alert-danger">{{ session('error') }}</div>
    @endif

    <table class="table">
        <thead>
            <tr>
                <th>Nombre</th>
                <th>Email</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Última conexión</th>
                <th>Registro</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            @foreach($users as $user)
            <tr>
                <td>{{ $user->name }}</td>
                <td>{{ $user->email }}</td>
                <td>
                    <span class="badge bg-{{ $user->role == 'superadmin' ? 'danger' : ($user->role == 'admin' ? 'warning' : 'info') }}">
                        {{ ucfirst($user->role) }}
                    </span>
                </td>
                <td>
                    <span class="badge bg-{{ $user->is_active ? 'success' : 'secondary' }}">{{ $user->is_active ? 'Activo' : 'Inactivo' }}</span>
                </td>
                <td>{{ $user->last_login_at?->format('d/m/Y H:i') ?? '—' }}</td>
                <td>{{ $user->created_at->format('d/m/Y') }}</td>
                <td>
                    @if(auth()->user()->hasPermission('users.edit'))
                    <a href="{{ url('/admin/usuarios/' . $user->id . '/editar') }}" class="btn btn-sm btn-warning">Editar</a>
                    @endif
                    @if($user->id !== auth()->id() && auth()->user()->hasPermission('users.delete'))
                    <form action="{{ url('/admin/usuarios/' . $user->id) }}" method="POST" style="display:inline">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="btn btn-sm btn-danger" data-confirm="Eliminar este usuario?">Eliminar</button>
                    </form>
                    @endif
                </td>
            </tr>
            @endforeach
        </tbody>
    </table>
    {{ $users->links() }}
</div>
@endsection
