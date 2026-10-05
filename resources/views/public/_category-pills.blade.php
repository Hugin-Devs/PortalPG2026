{{-- Pills de categorías (móvil/tablet) --}}
<div class="d-lg-none mb-3">
    <div class="d-flex flex-nowrap gap-2 overflow-auto pb-2">
        <a href="{{ route('news.index') }}" class="btn btn-sm rounded-pill text-nowrap {{ empty($activeSlug) ? 'btn-primary' : 'btn-outline-primary' }}">Todas</a>
        @foreach($categories as $cat)
            <a href="{{ route('news.category', $cat->slug) }}" class="btn btn-sm rounded-pill text-nowrap {{ ($activeSlug ?? null) === $cat->slug ? 'btn-primary' : 'btn-outline-primary' }}">{{ $cat->name }}</a>
        @endforeach
    </div>
</div>
