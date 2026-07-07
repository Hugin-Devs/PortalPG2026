<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Portal GDC')</title>
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <header>
        <nav class="navbar navbar-expand-lg navbar-light bg-white border-bottom">
            <div class="container">
                <a class="navbar-brand d-flex align-items-center" href="{{ url('/') }}">
                    <img src="{{ asset('images/logo.png') }}" alt="Logo GDC" height="60" class="me-3">
                    <span class="text-primary fw-bold fs-5 border-start border-primary ps-3">Gobierno de Distrito Capital</span>
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav ms-auto">
                        <li class="nav-item"><a class="nav-link" href="{{ url('/') }}">Inicio</a></li>
                        <li class="nav-item"><a class="nav-link" href="{{ url ('/admin/login') }}">Gestión</a></li>
                        <li class="nav-item dropdown">
                            <a class="nav-link dropdown-toggle" href="#" id="serviciosDropdown" role="button" data-bs-toggle="dropdown">Intranet</a>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <li><a class="dropdown-item" target="_blank" href="https://www.gdc.gob.ve/extranet/">Funcionarios Activos</a></li>
                                <li><a class="dropdown-item" href="{{ url ('#') }}">Jubilados</a></li>
                                <!-- 
                                <li><a class="dropdown-item" href="{{ url ('#') }}">Intranet</a></li>
                                <li><a class="dropdown-item" href="{{ url ('#') }}">Intranet</a></li>
                                -->
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
    </header>

    <main class="py-4">
        @yield('content')
    </main>

    <footer class="bg-dark text-white text-center py-3">
        <div class="container">
            <p>&copy; {{ date('Y') }} GDC. Todos los derechos reservados.</p>
        </div>
    </footer>
</body>
</html>