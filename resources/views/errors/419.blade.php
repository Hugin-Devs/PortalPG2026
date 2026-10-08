@extends('layouts.public')

@section('title', 'Sesión expirada')

@section('content')
@include('errors._box', [
    'code' => 419,
    'title' => 'Sesión expirada',
    'message' => 'Tu sesión o el token de seguridad han expirado. Recarga la página e intenta enviar el formulario nuevamente; si el problema continúa, cierra y vuelve a abrir el navegador.',
])
@endsection
