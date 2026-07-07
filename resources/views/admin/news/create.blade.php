@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Nueva Noticia</h1>
    <form action="{{ url('/admin/noticias') }}" method="POST">
        @csrf
        <div class="mb-3">
            <label>Título</label>
            <input type="text" name="title" class="form-control" required>
        </div>
        <div class="mb-3">
            <label>Categoría</label>
            <select name="category_id" class="form-control" required>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label>Extracto</label>
            <textarea name="excerpt" class="form-control" required></textarea>
        </div>
        <div class="mb-3">
            <label>Contenido</label>
            <textarea name="content" class="form-control" rows="10" required></textarea>
        </div>
        <button type="submit" class="btn btn-primary">Guardar</button>
    </form>
</div>
@endsection