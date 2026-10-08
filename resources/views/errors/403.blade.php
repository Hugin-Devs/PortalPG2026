@extends('layouts.public')

@section('title', 'Error 403')

@section('content')
@include('errors._box', [
    'code' => 403,
    'title' => 'Acceso denegado',
    'message' => 'No tienes los permisos necesarios para ver esta sección. Si crees que esto es un error, contacta al administrador del portal.',
])
@endsection
