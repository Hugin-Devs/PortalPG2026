@extends('layouts.public')

@section('title', 'Error 408 - Portal GDC')

@section('content')
@include('errors._box', [
    'code' => 408,
    'title' => 'Tiempo de espera agotado',
    'message' => 'La petición tardó demasiado en completarse y fue interrumpida. Intenta nuevamente.',
])
@endsection
