<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    @php
        $site = \App\Models\Setting::values();
        $siteTitle = $site['site_name'] !== '' ? $site['site_name'] : 'Portal GDC';
        $siteLogo = $site['site_logo'] !== '' ? 'storage/' . $site['site_logo'] : 'images/logo.png';
    @endphp
    <title>Admin - {{ $siteTitle }}</title>
    @vite(['resources/css/app.scss', 'resources/js/app.js'])
</head>
<body>
    <div class="d-flex">
        <aside class="bg-dark text-white p-3" style="width: 250px; min-height: 100vh;">
            <div class="text-center mb-3">
                <x-img path="{{ $siteLogo }}" alt="GDC" height="50" />
                <h5 class="mt-2">Admin GDC</h5>
            </div>
            <hr>
            <ul class="nav flex-column">
                @php
                    $u = auth()->user();
                    $inNews = $u->hasAnyPermission([
                        'news.view', 'news.create', 'news.edit', 'news.delete',
                        'media.view', 'media.upload', 'media.delete',
                        'videos.view', 'videos.upload', 'videos.manage', 'videos.delete',
                        'gacetas.view', 'gacetas.create', 'gacetas.edit', 'gacetas.delete',
                    ]);
                    $inPortal = $u->hasAnyPermission([
                        'categories.view', 'partners.view', 'about.edit',
                        'contact.edit', 'contact.view', 'contact.delete',
                        'org.view', 'org.create', 'org.edit', 'org.delete',
                        'rrss.manage',
                    ]);
                    $inConfig = $u->hasAnyPermission([
                        'settings.manage', 'users.view', 'users.create', 'users.edit', 'users.delete',
                        'roles.manage', 'audit.view',
                    ]);
                    $showNews = $u->hasPermission('news.view');
                    $showMedia = $u->hasPermission('media.view');
                    $showVideos = $u->hasPermission('videos.view');
                    $showMultimedia = $showMedia || $showVideos;
                    $mmOpen = request()->routeIs('admin.media.*', 'admin.videos.*');
                @endphp
                <li class="nav-item"><a class="nav-link text-white" href="{{ url('/admin') }}">Dashboard</a></li>
                @if($inNews)
                <li class="nav-item mt-3 mb-1"><span class="px-3 text-uppercase" style="font-size:.7rem; letter-spacing:.05em; color:#adb5bd;">Gestión Noticias</span></li>
                @endif
                @if($showNews)
                <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.news.*') ? 'active' : '' }}" href="{{ url('/admin/noticias') }}">Noticias</a></li>
                @endif
                @if($showMultimedia)
                <li class="nav-item">
                    <a class="nav-link text-white d-flex justify-content-between align-items-center {{ $mmOpen ? 'active' : '' }}" href="#mmMenu" data-bs-toggle="collapse" role="button" aria-expanded="{{ $mmOpen ? 'true' : 'false' }}">
                        Multimedia <i class="fa-solid fa-chevron-down small"></i>
                    </a>
                    <ul class="collapse nav flex-column flex-nowrap ps-3 {{ $mmOpen ? 'show' : '' }}" id="mmMenu">
                        @if($showMedia)
                        <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.media.*') ? 'active' : '' }}" href="{{ url('/admin/medios') }}">Medios</a></li>
                        @endif
                        @if($showVideos)
                        <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.videos.*') ? 'active' : '' }}" href="{{ url('/admin/videos') }}">Videos</a></li>
                        @endif
                    </ul>
                </li>
                @endif
                @if(auth()->user()->hasPermission('gacetas.view'))
                <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.gacetas.*') ? 'active' : '' }}" href="{{ url('/admin/gacetas') }}">Gacetas</a></li>
                @endif
                @if($inPortal)
                <li class="nav-item mt-3 mb-1"><span class="px-3 text-uppercase" style="font-size:.7rem; letter-spacing:.05em; color:#adb5bd;">Gestión Portal</span></li>
                @endif
                @if(auth()->user()->hasPermission('categories.view'))
                <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.categories.*') ? 'active' : '' }}" href="{{ url('/admin/categorias') }}">Categorías</a></li>
                @endif
                @if(auth()->user()->hasPermission('partners.view'))
                <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.partners.*') ? 'active' : '' }}" href="{{ url('/admin/entes') }}">Entes Afiliados</a></li>
                @endif
                @if(auth()->user()->hasPermission('about.edit'))
                <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.about.*') ? 'active' : '' }}" href="{{ url('/admin/nosotros') }}">Sobre Nosotros</a></li>
                @endif
                @if(auth()->user()->hasPermission('contact.edit'))
                <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.contact.edit', 'admin.contact.update') ? 'active' : '' }}" href="{{ url('/admin/contacto') }}">Contacto</a></li>
                @endif
                @if(auth()->user()->hasPermission('contact.view'))
                <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.contact.messages', 'admin.contact.message.*') ? 'active' : '' }}" href="{{ url('/admin/contacto/mensajes') }}">Mensajes</a></li>
                @endif
                @if(auth()->user()->hasPermission('org.view'))
                <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.org-members.*') ? 'active' : '' }}" href="{{ url('/admin/estructura') }}">Estructura Org.</a></li>
                @endif
                @if(auth()->user()->hasPermission('rrss.manage'))
                <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.rrss.*') ? 'active' : '' }}" href="{{ url('/admin/rrss') }}">Redes Sociales</a></li>
                @endif
                @if($inConfig)
                <li class="nav-item mt-3 mb-1"><span class="px-3 text-uppercase" style="font-size:.7rem; letter-spacing:.05em; color:#adb5bd;">Configuración Portal</span></li>
                @endif
                @if(auth()->user()->hasPermission('settings.manage'))
                <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.settings.*') ? 'active' : '' }}" href="{{ url('/admin/configuracion') }}">Identidad / Enlaces</a></li>
                @endif
                @if(auth()->user()->hasPermission('users.view'))
                <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.users.*') ? 'active' : '' }}" href="{{ url('/admin/usuarios') }}">Usuarios</a></li>
                @endif
                @if(auth()->user()->hasPermission('roles.manage'))
                <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.roles.*') ? 'active' : '' }}" href="{{ url('/admin/roles') }}">Roles y Permisos</a></li>
                @endif
                @if(auth()->user()->hasPermission('audit.view'))
                <li class="nav-item"><a class="nav-link text-white {{ request()->routeIs('admin.audit.*') ? 'active' : '' }}" href="{{ url('/admin/auditoria') }}">Auditoría</a></li>
                @endif
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
