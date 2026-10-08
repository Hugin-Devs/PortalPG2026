@extends('layouts.admin')

@section('content')
<div class="container">
    <style>
        .dash-bar { min-height: 2px; border-radius: 3px 3px 0 0; }
    </style>

    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="mb-0">Dashboard</h1>
            <small class="text-muted">Bienvenido, {{ auth()->user()->name }} · {{ auth()->user()->role }}</small>
        </div>
    </div>

    @php $u = auth()->user(); @endphp

    <div class="row g-3 mb-4">
        @if($u->hasPermission('news.view'))
        <a href="{{ url('/admin/noticias') }}" class="col-6 col-md-4 col-xl-3 text-reset" data-stat="noticias">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-uppercase text-muted">Noticias</span>
                        <i class="fa-solid fa-newspaper text-primary"></i>
                    </div>
                    <div class="fs-2 fw-bold stat-number">{{ $postStats->total }}</div>
                    <div class="small text-muted">{{ $postStats->published }} publicadas · {{ $postStats->drafts }} borradores</div>
                </div>
            </div>
        </a>

        <div class="col-6 col-md-4 col-xl-3" data-stat="visitas">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-uppercase text-muted">Visitas del mes</span>
                        <i class="fa-solid fa-chart-column text-primary"></i>
                    </div>
                    <div class="fs-2 fw-bold stat-number">{{ $monthViews }}</div>
                    <div class="small text-muted">Histórico: {{ number_format($viewsTotal) }} vistas de artículos</div>
                </div>
            </div>
        </div>
        @endif

        @if($u->hasPermission('users.view'))
        <a href="{{ url('/admin/usuarios') }}" class="col-6 col-md-4 col-xl-3 text-reset" data-stat="usuarios">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-uppercase text-muted">Usuarios</span>
                        <i class="fa-solid fa-users text-primary"></i>
                    </div>
                    <div class="fs-2 fw-bold stat-number">{{ $userStats->total }}</div>
                    <div class="small text-muted">{{ $userStats->active }} activos</div>
                </div>
            </div>
        </a>
        @endif

        @if($u->hasPermission('contact.view'))
        <a href="{{ url('/admin/contacto/mensajes') }}" class="col-6 col-md-4 col-xl-3 text-reset" data-stat="mensajes">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-uppercase text-muted">Mensajes sin leer</span>
                        <i class="fa-solid fa-envelope {{ $newContacts > 0 ? 'text-danger' : 'text-primary' }}"></i>
                    </div>
                    <div class="fs-2 fw-bold stat-number {{ $newContacts > 0 ? 'text-danger' : '' }}">{{ $newContacts }}</div>
                    <div class="small text-muted">Bandeja de contacto</div>
                </div>
            </div>
        </a>
        @endif

        @if($u->hasPermission('gacetas.view'))
        <a href="{{ url('/admin/gacetas') }}" class="col-6 col-md-4 col-xl-3 text-reset" data-stat="gacetas">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-uppercase text-muted">Gacetas</span>
                        <i class="fa-solid fa-file-lines text-primary"></i>
                    </div>
                    <div class="fs-2 fw-bold stat-number">{{ $counts['gacetas'] }}</div>
                    <div class="small text-muted">Oficiales cargadas</div>
                </div>
            </div>
        </a>
        @endif

        @if($u->hasPermission('media.view'))
        <a href="{{ url('/admin/medios') }}" class="col-6 col-md-4 col-xl-3 text-reset" data-stat="medios">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-uppercase text-muted">Medios</span>
                        <i class="fa-solid fa-images text-primary"></i>
                    </div>
                    <div class="fs-2 fw-bold stat-number">{{ $counts['media'] }}</div>
                    <div class="small text-muted">Archivos en biblioteca</div>
                </div>
            </div>
        </a>
        @endif

        @if($u->hasPermission('partners.view'))
        <a href="{{ url('/admin/entes') }}" class="col-6 col-md-4 col-xl-3 text-reset" data-stat="entes">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-uppercase text-muted">Entes afiliados</span>
                        <i class="fa-solid fa-building text-primary"></i>
                    </div>
                    <div class="fs-2 fw-bold stat-number">{{ $counts['partners'] }}</div>
                    <div class="small text-muted">{{ $counts['partnersActive'] }} activos</div>
                </div>
            </div>
        </a>
        @endif

        @if($u->hasPermission('videos.view'))
        <a href="{{ url('/admin/videos') }}" class="col-6 col-md-4 col-xl-3 text-reset" data-stat="videos">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-uppercase text-muted">Videos</span>
                        <i class="fa-solid fa-video text-primary"></i>
                    </div>
                    <div class="fs-2 fw-bold stat-number">{{ $counts['videos'] }}</div>
                    <div class="small text-muted">Institucionales</div>
                </div>
            </div>
        </a>
        @endif

        @if($u->hasPermission('categories.view'))
        <a href="{{ url('/admin/categorias') }}" class="col-6 col-md-4 col-xl-3 text-reset" data-stat="categorias">
            <div class="card h-100">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <span class="small text-uppercase text-muted">Categorías</span>
                        <i class="fa-solid fa-folder text-primary"></i>
                    </div>
                    <div class="fs-2 fw-bold stat-number">{{ $counts['categories'] }}</div>
                    <div class="small text-muted">Del portal</div>
                </div>
            </div>
        </a>
        @endif
    </div>

    @if($u->hasPermission('news.view'))
    <div class="card mb-4" data-chart="daily">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h5 class="card-title mb-0">Visitas a artículos — últimos 30 días</h5>
                <span class="small text-muted">{{ number_format($chartTotal) }} en el período</span>
            </div>
            @if($chartTotal === 0)
                <p class="text-muted mb-0">Sin visitas registradas aún. El conteo diario comienza con la activación de esta función.</p>
            @else
                <div class="d-flex align-items-end gap-1" style="height: 140px;">
                    @foreach($chartDays as $date => $count)
                        <div class="dash-bar flex-fill {{ $count > 0 ? 'bg-primary' : 'bg-light' }}"
                             style="height: {{ $count > 0 ? max(4, (int) round($count / $chartMax * 100)) : 0 }}%;"
                             title="{{ \Carbon\Carbon::parse($date)->format('d/m') }}: {{ $count }} visita{{ $count === 1 ? '' : 's' }}"></div>
                    @endforeach
                </div>
                <div class="d-flex justify-content-between small text-muted mt-1">
                    <span>{{ \Carbon\Carbon::parse(array_key_first($chartDays))->format('d/m') }}</span>
                    <span>{{ \Carbon\Carbon::parse(array_key_last($chartDays))->format('d/m') }}</span>
                </div>
            @endif
        </div>
    </div>

    <div class="card mb-4" data-stat="ultimas">
        <div class="card-body">
            <h5 class="card-title">Últimas noticias publicadas</h5>
            @if($latestPosts->isEmpty())
                <p class="text-muted mb-0">No hay noticias publicadas.</p>
            @else
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead>
                            <tr>
                                <th>Título</th>
                                <th>Categoría</th>
                                <th>Publicada</th>
                                <th class="text-end">Vistas</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($latestPosts as $p)
                                <tr>
                                    <td>{{ $p->title }}</td>
                                    <td>{{ $p->category?->name ?? '—' }}</td>
                                    <td>{{ $p->published_at?->format('d/m/Y H:i') ?? '—' }}</td>
                                    <td class="text-end"><i class="fa-solid fa-eye"></i> {{ number_format($p->views) }}</td>
                                    <td class="text-end">
                                        @if($u->hasPermission('news.edit'))
                                            <a href="{{ url('/admin/noticias/' . $p->id . '/editar') }}" class="btn btn-sm btn-outline-primary">Editar</a>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>
    </div>
    @endif
</div>
@endsection
