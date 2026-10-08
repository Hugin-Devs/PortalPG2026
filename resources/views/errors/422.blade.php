@extends('layouts.public')

@section('title', 'Error 422')

@section('content')
@include('errors._box', [
    'code' => 422,
    'title' => 'Datos no válidos',
    'message' => 'La información enviada no pudo ser procesada. Revisa los campos del formulario e inténtalo de nuevo.',
])
@endsection
