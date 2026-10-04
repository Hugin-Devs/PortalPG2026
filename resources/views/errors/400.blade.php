@extends('layouts.public')

@section('title', 'Error 400 - Portal GDC')

@section('content')
@include('errors._box', [
    'code' => 400,
    'title' => 'Solicitud no válida',
    'message' => 'El navegador envió una petición que el servidor no pudo procesar. Intenta nuevamente desde el botón de volver o recarga la página.',
])
@endsection
