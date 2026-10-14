@extends('layouts.admin')

@section('content')
<div class="container">
    <h1>Redes Sociales</h1>
    <p class="text-muted">Íconos de redes sociales que se muestran en el pie del sitio.</p>

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
                <p class="text-muted small">Se muestran como íconos en el pie del sitio. <strong>Una URL por línea</strong> — si una red tiene varias cuentas, escriba varias líneas y se dibujará un ícono por cuenta. Deje un campo vacío para ocultar esa red. Cada línea debe incluir <code>https://</code>.</p>

                <div class="row">
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold"><i class="fa-brands fa-facebook-f me-1"></i> Facebook</label>
                        <textarea name="facebook" rows="3" maxlength="4000" class="form-control @error('facebook') is-invalid @enderror" placeholder="https://www.facebook.com/&#10;https://www.facebook.com/otra-cuenta">{{ old('facebook', $social['facebook']) }}</textarea>
                        @error('facebook') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold"><i class="fa-brands fa-instagram me-1"></i> Instagram</label>
                        <textarea name="instagram" rows="3" maxlength="4000" class="form-control @error('instagram') is-invalid @enderror" placeholder="https://www.instagram.com/&#10;https://www.instagram.com/otra-cuenta">{{ old('instagram', $social['instagram']) }}</textarea>
                        @error('instagram') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold"><i class="fa-brands fa-youtube me-1"></i> YouTube</label>
                        <textarea name="youtube" rows="3" maxlength="4000" class="form-control @error('youtube') is-invalid @enderror" placeholder="https://www.youtube.com/@...">{{ old('youtube', $social['youtube']) }}</textarea>
                        @error('youtube') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold"><i class="fa-brands fa-x-twitter me-1"></i> X (Twitter)</label>
                        <textarea name="x" rows="3" maxlength="4000" class="form-control @error('x') is-invalid @enderror" placeholder="https://x.com/...">{{ old('x', $social['x']) }}</textarea>
                        @error('x') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold"><i class="fa-brands fa-threads me-1"></i> Threads</label>
                        <textarea name="threads" rows="3" maxlength="4000" class="form-control @error('threads') is-invalid @enderror" placeholder="https://www.threads.net/@...">{{ old('threads', $social['threads']) }}</textarea>
                        @error('threads') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold"><i class="fa-brands fa-telegram me-1"></i> Telegram</label>
                        <textarea name="telegram" rows="3" maxlength="4000" class="form-control @error('telegram') is-invalid @enderror" placeholder="https://t.me/...">{{ old('telegram', $social['telegram']) }}</textarea>
                        @error('telegram') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                    <div class="col-md-6 mb-3">
                        <label class="fw-bold"><i class="fa-brands fa-whatsapp me-1"></i> WhatsApp</label>
                        <textarea name="whatsapp" rows="3" maxlength="4000" class="form-control @error('whatsapp') is-invalid @enderror" placeholder="https://wa.me/...&#10;https://wa.me/58...">{{ old('whatsapp', $social['whatsapp']) }}</textarea>
                        @error('whatsapp') <div class="invalid-feedback">{{ $message }}</div> @enderror
                    </div>
                </div>
            </div>
        </div>

        <button type="submit" class="btn btn-primary">Guardar Cambios</button>
    </form>
</div>
@endsection
