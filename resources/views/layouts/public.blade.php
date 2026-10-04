<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Portal GDC')</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <header class="header">
        <nav class="navbar navbar-expand-lg navbar-light border-bottom">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="{{ url('/') }}">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo GDC" height="60" class="me-3">
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
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('entes.*') ? 'active' : '' }}" href="{{ route('entes.index') }}">Entes</a></li>
                        <li class="nav-item"><a class="nav-link {{ request()->routeIs('about.index') ? 'active' : '' }}" href="{{ route('about.index') }}">Sobre Nosotros</a></li>
                        <!-- <li class="nav-item"><a class="nav-link" href="{{ route('contact.index') }}">Contacto</a></li> -->
                        <!--
                        <li class="nav-item"><a class="nav-link" href="{{ url('/admin/login') }}">Gestión</a></li>
                        -->
                        <li class="nav-item dropdown">
                            <a class="nav-link" href="#" id="serviciosDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false" aria-haspopup="true">Intranet GDC</a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" target="_blank" rel="noopener" href="https://www.gdc.gob.ve/extranet/">Funcionarios Activos</a></li>
                                <li><a class="dropdown-item" href="#">Jubilados</a></li>
                                <li><a class="dropdown-item" href="{{ url('/admin/login') }}">Gestión Aplicaciones</a></li>
                            </ul>
                        </li>
                        <li class="nav-item d-flex align-items-center ms-lg-3">
                            @include('public._social-links', ['linkClass' => 'text-primary', 'ulClass' => 'gap-2'])
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="py-4" style="background: rgba(255,255,255,0.92);">
        @yield('content')
    </main>

    <footer class="pt-4 pb-3" style="background: url('{{ asset('images/Banner_final.png') }}') center/cover no-repeat !important; color: #212529;">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <h5 style="color: #212529;">Gobierno de Distrito Capital</h5>
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
                        <li><a href="{{ route('entes.index') }}" class="text-decoration-none footer-link"><i class="fas fa-arrow-right text-primary me-1"></i><span class="footer-text">Entes</span></a></li>
                        <li><a href="{{ route('contact.index') }}" class="text-decoration-none footer-link"><i class="fas fa-arrow-right text-primary me-1"></i><span class="footer-text">Contacto</span></a></li>
                        <li><a href="{{ route('about.index') }}" class="text-decoration-none footer-link"><i class="fas fa-arrow-right text-primary me-1"></i><span class="footer-text">Sobre Nosotros</span></a></li>
                    </ul>
                </div>
                <div class="col-md-3">
                    <h6 style="color: #212529;">Entes Afiliados</h6>
                    <div class="d-flex flex-wrap gap-2">
                        @foreach(\App\Models\Partner::where('is_active', true)->orderBy('order')->get() as $partner)
                            @if($partner->url)
                                <a href="{{ $partner->url }}" target="_blank" title="{{ $partner->name }}">
                            @endif
                            @if($partner->logo)
                                <img src="{{ asset('storage/' . $partner->logo) }}" alt="{{ $partner->name }}" height="30" class="bg-white rounded p-1">
                            @else
                                <span class="small" style="color: #212529;">{{ $partner->name }}</span>
                            @endif
                            @if($partner->url)
                                </a>
                            @endif
                        @endforeach
                    </div>
                </div>
            </div>
            <hr class="my-3" style="border-color: #212529;">
            <p class="text-center small mb-0" style="color: #212529;">&copy; {{ date('Y') }} GDC. Todos los derechos reservados.</p>
        </div>
    </footer>
</body>
</html>
