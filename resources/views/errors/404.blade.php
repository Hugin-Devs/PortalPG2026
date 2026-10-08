@extends('layouts.public')

@section('title', 'Error 404')

@section('content')
@include('errors._box', [
    'code' => 404,
    'title' => 'Página no encontrada',
    'message' => 'La página que buscas no existe, fue movida o el enlace está roto. Puedes volver a la página anterior o ir al inicio del portal.',
])
@endsection
