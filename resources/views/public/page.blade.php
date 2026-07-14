@extends('layouts.public')

@section('title', $page->title . ' - Portal GDC')

@section('content')
<div class="container my-5">
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="{{ url('/') }}">Inicio</a></li>
            <li class="breadcrumb-item active" aria-current="page">{{ $page->title }}</li>
        </ol>
    </nav>

    <h1 class="text-primary fw-bold mb-4">{{ $page->title }}</h1>

    <div class="content fs-5 lh-lg">
        {!! nl2br(e($page->content)) !!}
    </div>
</div>
@endsection
