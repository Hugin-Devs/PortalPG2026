@extends('layouts.public')

@section('title', $service->title . ' - Portal GDC')

@section('content')
<div class="container my-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
            <li class="breadcrumb-item"><a href="{{ route('services.index') }}">Servicios</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $service->title }}</li>
        </ol>
    </nav>

    <div class="row">
        <div class="col-lg-8">
            <h1 class="text-primary fw-bold mb-4">{{ $service->title }}</h1>

            @if($service->description)
                <p class="lead mb-4">{{ $service->description }}</p>
            @endif

            <div class="content fs-5 lh-lg">
                {!! nl2br(e($service->content)) !!}
            </div>

            @if($service->external_url)
                <a href="{{ $service->external_url }}" target="_blank" class="btn btn-primary mt-4">Acceder al servicio →</a>
            @endif
        </div>
    </div>
</div>
@endsection
