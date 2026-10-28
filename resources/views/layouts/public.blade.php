<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $site = \App\Models\Setting::values();
        $siteTitle = $site['site_name'] !== '' ? $site['site_name'] : 'Portal GDC';
        $siteLogo = $site['site_logo'] !== '' ? 'storage/' . $site['site_logo'] : 'images/logo.png';
        $sectionTitle = trim($__env->yieldContent('title'));
        $pageTitle = $seo['title'] ?? ($sectionTitle !== '' ? $sectionTitle : '');
        $metaDescription = $seo['description'] ?? 'Portal institucional del Gobierno de Distrito Capital. Noticias, gacetas oficiales, entes afiliados e información de contacto.';
        $metaImage = $seo['image'] ?? $siteLogo;
        $metaUrl = $seo['url'] ?? url()->current();
        $metaType = $seo['type'] ?? 'website';
        $metaTitle = $pageTitle !== '' ? $pageTitle . ' - ' . $siteTitle : $siteTitle;
    @endphp
    <title>{{ $metaTitle }}</title>
    <meta name="description" content="{{ $metaDescription }}">
    <link rel="canonical" href="{{ $metaUrl }}">
    <meta property="og:title" content="{{ $metaTitle }}">
    <meta property="og:description" content="{{ $metaDescription }}">
    <meta property="og:image" content="{{ asset($metaImage) }}">
    <meta property="og:url" content="{{ $metaUrl }}">
    <meta property="og:type" content="{{ $metaType }}">
    <meta property="og:locale" content="es_VE">
    <meta name="twitter:card" content="summary_large_image">
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <header class="header">
        <nav class="navbar navbar-expand-lg navbar-light border-bottom">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="{{ url('/') }}">
                    <x-img path="{{ $siteLogo }}" alt="Logo GDC" height="60" class="me-3" />
                    <span class="text-primary fw-bold fs-5 border-start border-primary ps-3">Gobierno de Distrito Capital</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-label="Alternar navegación" aria-controls="navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse flex-column" id="navbarNav">
                    <form class="d-flex w-100 my-2 my-lg-0 pb-2 border-bottom border-light" action="{{ route('search') }}" method="GET">
                        <input class="form-control form-control-sm me-2" type="search" name="q" placeholder="Buscar..." aria-label="Buscar" required>
                        <button class="btn btn-outline-primary btn-sm" type="submit">Buscar</button>
                    </form>
                    <ul class="navbar-nav ms-auto w-100 justify-content-end">
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ url('/') }}">Inicio</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('news.*') ? 'active' : '' }}" href="{{ route('news.index') }}">Noticias</a></li>
                        {{-- Oculto temporalmente: módulo de entes en preparación por equipo externo.
                             Reactivar descomentando. Las rutas /entes siguen vivas. --}}
                        {{-- <li class="nav-item"><a class="nav-link {{ request()->routeIs('entes.*') ? 'active' : '' }}" href="{{ route('entes.index') }}">Entes</a></li> --}}
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('about.index') ? 'active' : '' }}" href="{{ route('about.index') }}">Sobre Nosotros</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('gacetas.*') ? 'active' : '' }}" href="{{ route('gacetas.index') }}">Gacetas Oficiales</a></li>
                        <li class="nav-item dropdown">
                            <a class="nav-link" href="#" id="serviciosDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true">Intranet GDC</a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                @if($site['nav_funcionarios_url'] !== '')
                                    <li><a class="dropdown-item" target="_blank" rel="noopener" href="{{ $site['nav_funcionarios_url'] }}">Funcionarios Activos</a></li>
                                @endif
                                @if($site['nav_jubilados_url'] !== '')
                                    <li><a class="dropdown-item" target="_blank" rel="noopener" href="{{ $site['nav_jubilados_url'] }}">Jubilados</a></li>
                                @endif
                                @if($site['nav_intranet_url'] !== '')
                                    <li><a class="dropdown-item" target="_blank" rel="noopener" href="{{ $site['nav_intranet_url'] }}">Intranets GDC</a></li>
                                @endif
                                <li><a class="dropdown-item" href="{{ url('/admin/login') }}">Gestión Aplicaciones</a></li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="py-4" style="background: rgba(255,255,255,0.92);">
        @yield('content')
    </main>

    <footer class="pt-4 pb-3" style="background: url('{{ asset('images/Banner_Final.png') }}') center/cover no-repeat !important; color: #212529;">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 style="color: #212529;">Gobierno del Distrito Capital</h5>
                    <p class="small">Portal institucional oficial</p>
                    <div class="mt-2">
                        @include('public._social-links', ['linkClass' => 'text-dark', 'ulClass' => 'gap-3'])
                    </div>
                </div>
                <div class="col-md-3">
                    <h6 style="color: #212529;">Enlaces</h6>
                    <ul class="list-unstyled small">
                        <li><a href="{{ url('/') }}" class="text-decoration-none footer-link"><i class="fas fa-arrow-right text-primary me-1"></i><span class="footer-text">Inicio</span></a></li>
                        <li><a href="{{ route('news.index') }}" class="text-decoration-none footer-link"><i class="fas fa-arrow-right text-primary me-1"></i><span class="footer-text">Noticias</span></a></li>
                        {{-- Oculto temporalmente: módulo de entes en preparación por equipo externo. Reactivar descomentando. --}}
                        {{-- <li><a href="{{ route('entes.index') }}" class="text-decoration-none footer-link"><i class="fas fa-arrow-right text-primary me-1"></i><span class="footer-text">Entes</span></a></li> --}}
                        <li><a href="{{ route('about.index') }}" class="text-decoration-none footer-link"><i class="fas fa-arrow-right text-primary me-1"></i><span class="footer-text">Sobre Nosotros</span></a></li>
                        <li><a href="{{ route('gacetas.index') }}" class="text-decoration-none footer-link"><i class="fas fa-arrow-right text-primary me-1"></i><span class="footer-text">Gacetas Oficiales</span></a></li>
                        <li><a href="{{ route('contact.index') }}" class="text-decoration-none footer-link"><i class="fas fa-arrow-right text-primary me-1"></i><span class="footer-text">Contacto</span></a></li>
                    </ul>
                </div>
                {{-- Bloque "Entes Afiliados" oculto temporalmente: módulo de entes en preparación
                     por equipo externo. Reactivar descomentando (incluye la consulta a Partner). --}}
                {{--
                <div class="col-md-3">
                    <h6 style="color: #212529;">Entes Afiliados</h6>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach(\App\Models\Partner::where('is_active', true)->orderBy('order')->get() as $partner)
                            @if($partner->url)
                                <a href="{{ $partner->url }}" target="_blank" title="{{ $partner->name }}">
                            @endif
                            @if($partner->logo)
                                <x-img path="storage/{{ $partner->logo }}" alt="{{ $partner->name }}" height="30" loading="lazy" decoding="async" class="bg-white rounded p-1" />
                            @else
                                <span class="small" style="color: #212529;">{{ $partner->name }}</span>
                            @endif
                            @if($partner->url)
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
                --}}
            </div>
            <hr class="my-3" style="border-color: #212529;">
            <p class="text-center small mb-0" style="color: #212529;">&copy; {{ date('Y') }} GDC, Dirección General de Tecnología, Todos los derechos reservados.</p>
        </div>
    </footer>
    <script nonce="{{ request()->attributes->get('csp_nonce') }}">
        document.querySelectorAll('[data-action="reload"]').forEach(function (btn) {
            btn.addEventListener('click', function () { window.location.reload(); });
        });
        document.querySelectorAll('[data-action="back"]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                if (history.length > 1) {
                    history.back();
                } else {
                    window.location.href = btn.dataset.fallback || '/';
                }
            });
        });
        document.querySelectorAll('[data-confirm]').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                if (! confirm(btn.dataset.confirm)) {
                    e.preventDefault();
                }
            });
        });
    </script>
</body>
</html>
