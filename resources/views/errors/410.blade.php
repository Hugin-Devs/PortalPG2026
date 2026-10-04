@extends('layouts.public')

@section('title', 'Error 410 - Portal GDC')

@section('content')
@include('errors._box', [
    'code' => 410,
    'title' => 'Contenido eliminado',
    'message' => 'El recurso que buscas ya no está disponible. Puede haber sido eliminado permanentemente.',
])
@endsection
