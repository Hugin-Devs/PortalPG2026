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
                                <input type="checkbox" class="form-check-input perm-checkbox"
                                       data-role="{{ $role }}"
                                       value="{{ $permission->id }}"
                                       {{ in_array($permission->id, $rolePermissions[$role] ?? []) ? 'checked' : '' }}>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    @foreach($roles as $role)
        <form id="role-form-{{ $role }}" action="{{ url('/admin/roles') }}" method="POST" style="display:none">
            @csrf
            <input type="hidden" name="role" value="{{ $role }}">
            <input type="hidden" name="permissions" id="permissions-input-{{ $role }}" value="">
        </form>
    @endforeach

    <p class="text-muted small mt-3">
        <strong>Nota:</strong> Marcar/desmarcar un permiso lo asigna o remueve inmediatamente para el rol seleccionado.
    </p>
</div>

<script>
document.querySelectorAll('.perm-checkbox').forEach(function(cb) {
    cb.addEventListener('change', function() {
        var role = this.dataset.role;
        var checked = document.querySelectorAll('.perm-checkbox[data-role="' + role + '"]:checked');
        var ids = [];
        checked.forEach(function(c) { ids.push(c.value); });
        document.getElementById('permissions-input-' + role).value = ids.join(',');
        document.getElementById('role-form-' + role).submit();
    });
});
</script>
@endsection
