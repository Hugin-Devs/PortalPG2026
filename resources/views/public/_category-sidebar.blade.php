{{-- Sidebar de categorías + buscador (desktop) --}}
<aside class="col-lg-3 d-none d-lg-block">
    <div class="card shadow-sm">
        <div class="card-body">
            <h5 class="card-title text-primary border-bottom border-primary pb-2">Categorías</h5>
            <form action="{{ route('search') }}" method="GET" class="mb-3">
                <div class="input-group">
                    <input class="form-control form-control-sm" type="search" name="q" placeholder="Buscar..." aria-label="Buscar" required>
                    <button class="btn btn-outline-primary btn-sm" type="submit">Buscar</button>
                </div>
            </form>
            <ul class="list-unstyled mb-0">
                <li class="mb-1">
                    <a href="{{ route('news.index') }}" class="text-decoration-none d-flex justify-content-between align-items-center {{ empty($activeSlug) ? 'text-primary fw-bold' : 'text-dark' }}">
                        <span>Todas</span>
                        <span class="badge bg-light text-dark border">{{ $categories->sum('published_count') }}</span>
                    </a>
                </li>
                @foreach($categories as $cat)
                <li class="mb-1">
                    <a href="{{ route('news.category', $cat->slug) }}" class="text-decoration-none d-flex justify-content-between align-items-center {{ ($activeSlug ?? null) === $cat->slug ? 'text-primary fw-bold' : 'text-dark' }}">
                        <span>{{ $cat->name }}</span>
                        <span class="badge bg-light text-dark border">{{ $cat->published_count }}</span>
                    </a>
                </li>
                @endforeach
            </ul>
        </div>
    </div>
</aside>
