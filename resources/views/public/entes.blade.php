@extends('layouts.public')

@section('title', 'Entes')

@section('content')
<div class="container my-5">
    <h1 class="text-primary fw-bold mb-4 border-bottom border-primary pb-2">ENTES</h1>

    @if($entes->isEmpty())
        <p class="text-secondary">Próximamente publicaremos los entes afiliados.</p>
    @else
    <div class="mb-4" style="max-width: 420px;">
        <div class="input-group">
            <span class="input-group-text bg-white"><i class="fa-solid fa-magnifying-glass text-secondary" aria-hidden="true"></i></span>
            <input type="search" id="enteSearch" class="form-control" placeholder="Buscar ente..." aria-label="Buscar entre los entes afiliados" autocomplete="off">
        </div>
    </div>
    <div class="row g-4" id="entesGrid">
        @foreach($entes as $ente)
        <div class="col-lg-3 col-md-4 col-sm-6" data-name="{{ $ente->name }}" data-desc="{{ $ente->description ?? '' }}">
            <div class="card h-100 border-0 shadow-sm text-center p-3">
                @if($ente->logo)
                    <div class="mb-3">
                        <x-img path="storage/{{ $ente->logo }}" alt="{{ $ente->name }}" height="70" loading="lazy" decoding="async" class="mx-auto" />
                    </div>
                @endif
                <h5 class="card-title text-primary">{{ $ente->name }}</h5>
                @if($ente->description)
                    <p class="card-text text-secondary small">{{ Str::limit($ente->description, 100) }}</p>
                @endif
                @if($ente->url)
                    <a href="{{ $ente->url }}" target="_blank" rel="noopener" class="btn btn-outline-primary btn-sm mt-auto">Ir al sitio →</a>
                @else
                    <a href="{{ route('entes.show', $ente->slug) }}" class="btn btn-outline-primary btn-sm mt-auto">Ver más →</a>
                @endif
            </div>
        </div>
        @endforeach
    </div>
    <p id="entesNoResults" class="d-none text-secondary mt-3">No se encontraron entes que coincidan con tu búsqueda.</p>
    @endif
</div>
<script>
    (function () {
        var input = document.getElementById('enteSearch');
        var grid = document.getElementById('entesGrid');
        var noResults = document.getElementById('entesNoResults');
        if (!input || !grid) return;

        function normalize(value) {
            return (value || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        input.addEventListener('input', function () {
            var query = normalize(input.value.trim());
            var cards = grid.children;
            var visible = 0;
            for (var i = 0; i < cards.length; i++) {
                var card = cards[i];
                var haystack = normalize(card.getAttribute('data-name') + ' ' + card.getAttribute('data-desc'));
                var matches = query === '' || haystack.indexOf(query) !== -1;
                card.classList.toggle('d-none', !matches);
                if (matches) visible++;
            }
            if (noResults) noResults.classList.toggle('d-none', visible > 0);
        });
    })();
</script>
@endsection
