@extends('layouts.public')

@section('title', 'Error 405 - Portal GDC')

@section('content')
@include('errors._box', [
    'code' => 405,
    'title' => 'Método no permitido',
    'message' => 'La acción solicitada no está permitida en esta dirección. Vuelve a la página anterior e inténtalo de nuevo.',
])
@endsection
