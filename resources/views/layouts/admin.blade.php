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
            <h3>Admin GDC</h3>
            <hr>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link text-white" href="{{ url('/admin') }}">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link text-white" href="{{ url('/admin/noticias') }}">Noticias</a></li>
            </ul>
        </aside>
        <main class="flex-grow-1 p-4">
            @yield('content')
        </main>
    </div>
</body>
</html>