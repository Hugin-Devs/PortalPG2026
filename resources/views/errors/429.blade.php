@extends('layouts.public')

@section('title', 'Error 429')

@section('content')
@include('errors._box', [
    'code' => 429,
    'title' => 'Demasiadas solicitudes',
    'message' => 'Has realizado demasiadas peticiones en muy poco tiempo. Espera unos segundos y vuelve a intentarlo.',
])
@endsection
