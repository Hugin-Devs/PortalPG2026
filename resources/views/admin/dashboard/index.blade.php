@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Dashboard Administrativo</h1>
    <p>Bienvenido, {{ auth()->user()->name }}.</p>
    <form action="{{ url('/admin/logout') }}" method="POST">
        @csrf
        <button type="submit" class="btn btn-danger">Cerrar Sesión</button>
    </form>
</div>
@endsection