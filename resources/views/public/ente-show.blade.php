@extends('layouts.public')

@section('title', $ente->name)

@section('content')
<div class="container my-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
            <li class="breadcrumb-item"><a href="{{ route('entes.index') }}">Entes</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $ente->name }}</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            @if($ente->logo)
                <x-img path="storage/{{ $ente->logo }}" alt="{{ $ente->name }}" height="90" class="mb-4 bg-white rounded p-2 border" />
            @endif

            <h1 class="text-primary fw-bold mb-4">{{ $ente->name }}</h1>

            @if($ente->description)
                <p class="lead mb-4">{!! nl2br(e($ente->description)) !!}</p>
            @endif

            @if($ente->url)
                <a href="{{ $ente->url }}" target="_blank" rel="noopener" class="btn btn-primary mt-4">Ir al sitio →</a>
            @endif
        </div>
    </div>
</div>
@endsection
