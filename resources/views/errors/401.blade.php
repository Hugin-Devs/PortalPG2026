@extends('layouts.public')

@section('title', 'Error 401 - Portal GDC')

@section('content')
@include('errors._box', [
    'code' => 401,
    'title' => 'No autorizado',
    'message' => 'Debes iniciar sesión para acceder a este contenido.',
])
@endsection
