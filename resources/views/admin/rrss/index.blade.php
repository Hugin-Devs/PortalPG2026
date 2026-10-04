@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Redes Sociales</h1>
    <p class="text-muted">Íconos de redes sociales que se muestran en el encabezado y el pie del sitio.</p>

    @if(session('success'))
        <div class="alert alert-success">{{ session('success') }}</div>
    @endif

    <form action="{{ url('/admin/rrss') }}" method="POST">
        @csrf
        @method('PUT')

        <div class="card mb-4">
            <div class="card-header fw-bold">
                <i class="fa-brands fa-share-nodes me-2"></i>Redes sociales
            </div>
            <div class="card-body">
                <p class="text-muted small">Se muestran como íconos en el encabezado y el pie del sitio. Deje un campo vacío para ocultar esa red. Incluya <code>https://</code> en cada URL.</p>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold"><i class="fa-brands fa-facebook-f me-1"></i> Facebook</label>
                        <input type="url" name="facebook" class="form-control @error('facebook') is-invalid @enderror" maxlength="255" placeholder="https://www.facebook.com/..." value="{{ old('facebook', $social['facebook']) }}">
                        @error('facebook') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold"><i class="fa-brands fa-instagram me-1"></i> Instagram</label>
                        <input type="url" name="instagram" class="form-control @error('instagram') is-invalid @enderror" maxlength="255" placeholder="https://www.instagram.com/..." value="{{ old('instagram', $social['instagram']) }}">
                        @error('instagram') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold"><i class="fa-brands fa-youtube me-1"></i> YouTube</label>
                        <input type="url" name="youtube" class="form-control @error('youtube') is-invalid @enderror" maxlength="255" placeholder="https://www.youtube.com/@..." value="{{ old('youtube', $social['youtube']) }}">
                        @error('youtube') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold"><i class="fa-brands fa-x-twitter me-1"></i> X (Twitter)</label>
                        <input type="url" name="x" class="form-control @error('x') is-invalid @enderror" maxlength="255" placeholder="https://x.com/..." value="{{ old('x', $social['x']) }}">
                        @error('x') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold"><i class="fa-brands fa-threads me-1"></i> Threads</label>
                        <input type="url" name="threads" class="form-control @error('threads') is-invalid @enderror" maxlength="255" placeholder="https://www.threads.net/@..." value="{{ old('threads', $social['threads']) }}">
                        @error('threads') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold"><i class="fa-brands fa-telegram me-1"></i> Telegram</label>
                        <input type="url" name="telegram" class="form-control @error('telegram') is-invalid @enderror" maxlength="255" placeholder="https://t.me/..." value="{{ old('telegram', $social['telegram']) }}">
                        @error('telegram') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold"><i class="fa-brands fa-whatsapp me-1"></i> WhatsApp</label>
                        <input type="url" name="whatsapp" class="form-control @error('whatsapp') is-invalid @enderror" maxlength="255" placeholder="https://wa.me/..." value="{{ old('whatsapp', $social['whatsapp']) }}">
                        @error('whatsapp') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
    </form>
</div>
@endsection
