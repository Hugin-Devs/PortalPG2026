@extends('layouts.admin')

@section('content')
<div class="d-flex justify-content-between align-items-center mb-4">
    <h1 class="h3 mb-0">Auditoría de actividad</h1>
    @can('audit.export')
        <a href="{{ route('admin.audit.export', request()->query()) }}" class="btn btn-outline-secondary btn-sm">
            <i class="fa-solid fa-file-csv"></i> Exportar CSV
        </a>
    @endcan
</div>

<div class="card mb-4">
    <div class="card-body">
        <form method="GET" action="{{ route('admin.audit.index') }}" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label for="f-action" class="form-label small mb-1">Acción</label>
                <select name="action" id="f-action" class="form-select form-select-sm">
                    <option value="">Todas</option>
                    @foreach ($actions as $action)
                        <option value="{{ $action }}" @selected(($filters['action'] ?? null) === $action)>{{ $action }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="f-user" class="form-label small mb-1">Usuario</label>
                <select name="user_id" id="f-user" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach ($users as $user)
                        <option value="{{ $user->id }}" @selected((string) ($filters['user_id'] ?? null) === (string) $user->id)>
                            {{ $user->name }} (#{{ $user->id }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="f-role" class="form-label small mb-1">Rol</label>
                <select name="role" id="f-role" class="form-select form-select-sm">
                    <option value="">Todos</option>
                    @foreach (['superadmin', 'admin', 'editor'] as $role)
                        <option value="{{ $role }}" @selected(($filters['role'] ?? null) === $role)>{{ $role }}</option>
                    @endforeach
                </select>
            </div>
            <div class="col-md-2">
                <label for="f-from" class="form-label small mb-1">Desde</label>
                <input type="date" name="date_from" id="f-from" value="{{ $filters['date_from'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label for="f-to" class="form-label small mb-1">Hasta</label>
                <input type="date" name="date_to" id="f-to" value="{{ $filters['date_to'] ?? '' }}" class="form-control form-control-sm">
            </div>
            <div class="col-md-2">
                <label for="f-q" class="form-label small mb-1">Buscar</label>
                <input type="text" name="q" id="f-q" value="{{ $filters['q'] ?? '' }}" maxlength="128" placeholder="Descripción, recurso, IP…" class="form-control form-control-sm">
            </div>
            <div class="col-12 d-flex gap-2">
                <button type="submit" class="btn btn-primary btn-sm"><i class="fa-solid fa-filter"></i> Filtrar</button>
                <a href="{{ route('admin.audit.index') }}" class="btn btn-outline-secondary btn-sm">Limpiar</a>
            </div>
        </form>
    </div>
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
                        <th>Detalles</th>
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
                            <td>
                                @if ($log->metadata || $log->user_agent)
                                    <button class="btn btn-link btn-sm p-0" type="button" title="Ver detalles" data-bs-toggle="collapse" data-bs-target="#log-details-{{ $log->id }}">
                                        <i class="fa-solid fa-magnifying-glass-plus"></i>
                                    </button>
                                    <div class="collapse" id="log-details-{{ $log->id }}">
                                        @if ($log->metadata)
                                            <pre class="small mb-1">{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) }}</pre>
                                        @endif
                                        @if ($log->user_agent)
                                            <div class="small text-muted">{{ $log->user_agent }}</div>
                                        @endif
                                    </div>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="text-center text-muted py-4">No hay registros de auditoría.</td>
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
