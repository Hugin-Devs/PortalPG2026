@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Roles y Permisos</h1>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <div class="table-responsive">
        <table class="table table-bordered">
            <thead>
                <tr>
                    <th style="width: 200px;">Permiso</th>
                    @foreach($roles as $role)
                        <th class="text-center text-capitalize">{{ $role }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @php $currentGroup = ''; @endphp
                @foreach($permissions as $permission)
                    @if($permission->group && $permission->group !== $currentGroup)
                        @php $currentGroup = $permission->group; @endphp
                        <tr class="table-secondary">
                            <td colspan="{{ count($roles) + 1 }}" class="fw-bold">{{ $currentGroup }}</td>
                        </tr>
                    @endif
                    <tr>
                        <td>{{ $permission->name }}</td>
                        @foreach($roles as $role)
                            <td class="text-center">
                                <form action="{{ url('/admin/roles') }}" method="POST">
                                    @csrf
                                    <input type="hidden" name="role" value="{{ $role }}">
                                    <input type="hidden" name="permissions[]" value="{{ $permission->id }}">
                                    <input type="checkbox" class="form-check-input permission-checkbox"
                                           data-role="{{ $role }}"
                                           data-permission="{{ $permission->id }}"
                                           {{ in_array($permission->id, $rolePermissions[$role] ?? []) ? 'checked' : '' }}
                                           onchange="this.form.submit()">
                                </form>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <p class="text-muted small mt-3">
        <strong>Nota:</strong> Marcar/desmarcar un permiso lo asigna o remueve inmediatamente para el rol seleccionado.
    </p>
</div>
@endsection
