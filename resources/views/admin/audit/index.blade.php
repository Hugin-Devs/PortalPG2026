@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3">Auditoría de actividad</h1>
</div>

<div class="card">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-striped table-hover mb-0">
                <thead class="table-dark">
                    <tr>
                        <th>Fecha</th>
                        <th>Usuario</th>
                        <th>Rol</th>
                        <th>Acción</th>
                        <th>Recurso</th>
                        <th>IP</th>
                        <th>Descripción</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($logs as $log)
                        <tr>
                            <td>{{ $log->created_at->format('d/m/Y H:i:s') }}</td>
                            <td>{{ $log->user->name }} <small class="text-muted">({{ $log->user->email }})</small></td>
                            <td><span class="badge bg-secondary">{{ $log->role ?? '—' }}</span></td>
                            <td><code>{{ $log->action }}</code></td>
                            <td>
                                @if ($log->entity_type)
                                    {{ class_basename($log->entity_type) }}#{{ $log->entity_id }}
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $log->ip_address }}</td>
                            <td>{{ $log->description }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="text-center text-muted py-4">No hay registros de auditoría.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    <div class="card-footer">
        {{ $logs->links() }}
    </div>
</div>
@endsection
