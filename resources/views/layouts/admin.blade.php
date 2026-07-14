<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin - Intranet GDC</title>
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="d-flex">
        <aside class="bg-dark text-white p-3" style="width: 250px; min-height: 100vh;">
            <div class="text-center mb-3">
                <img src="{{ asset('images/logo.png') }}" alt="GDC" height="50">
                <h5 class="mt-2">Admin GDC</h5>
            </div>
            <hr>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link text-white" href="{{ url('/admin') }}">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="{{ url('/admin/noticias') }}">Noticias</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="{{ url('/admin/categorias') }}">Categorías</a></li>
                <!-- <li class="nav-item"><a class="nav-link text-white" href="{{ url('/admin/servicios') }}">Servicios</a></li> -->
                <li class="nav-item"><a class="nav-link text-white" href="{{ url('/admin/entes') }}">Entes Afiliados</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="{{ url('/admin/usuarios') }}">Usuarios</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="{{ url('/admin/medios') }}">Medios</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="{{ url('/admin/nosotros') }}">Sobre Nosotros</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="{{ url('/admin/estructura') }}">Estructura Org.</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="{{ url('/admin/roles') }}">Roles y Permisos</a></li>
                <li class="nav-item mt-3">
                    <form action="{{ url('/admin/logout') }}" method="POST">
                        @csrf
                        <button type="submit" class="btn btn-outline-light btn-sm w-100">Cerrar Sesión</button>
                    </form>
                </li>
            </ul>
        </aside>
        <main class="flex-grow-1 p-4">
            @yield('content')
        </main>
    </div>
</body>
</html>
