@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Editar Noticia</h1>
    <form action="{{ url('/admin/noticias/' . $post->id) }}" method="POST">
        @csrf
        @method('PUT')
        <div class="mb-3">
            <label>Título</label>
            <input type="text" name="title" class="form-control" value="{{ old('title', $post->title) }}" required>
        </div>
        <div class="mb-3">
            <label>Categoría</label>
            <select name="category_id" class="form-control" required>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" {{ $post->category_id == $category->id ? 'selected' : '' }}>{{ $category->name }}</option>
                @endforeach
            </select>
        </div>
        <div class="mb-3">
            <label>Extracto</label>
            <textarea name="excerpt" class="form-control" required>{{ old('excerpt', $post->excerpt) }}</textarea>
        </div>
        <div class="mb-3">
            <label>Contenido</label>
            <textarea name="content" class="form-control" rows="10" required>{{ old('content', $post->content) }}</textarea>
        </div>
        <div class="mb-3 form-check">
            <input type="checkbox" name="is_featured" class="form-check-input" value="1" id="isFeatured" {{ $post->is_featured ? 'checked' : '' }}>
            <label class="form-check-label" for="isFeatured">Destacado</label>
        </div>
        <div class="mb-3">
            <label>Estado</label>
            <select name="status" class="form-control">
                <option value="draft" {{ $post->status == 'draft' ? 'selected' : '' }}>Borrador</option>
                <option value="published" {{ $post->status == 'published' ? 'selected' : '' }}>Publicado</option>
            </select>
        </div>
        <button type="submit" class="btn btn-primary">Actualizar</button>
        <a href="{{ url('/admin/noticias') }}" class="btn btn-secondary">Cancelar</a>
    </form>
</div>
@endsection
