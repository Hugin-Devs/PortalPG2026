<textarea name="content" id="content" class="form-control" rows="10" required>{{ $value ?? '' }}</textarea>
<script src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
<script>
    document.addEventListener('DOMContentLoaded', function () {
        tinymce.init({
            selector: '#content',
            language: 'es',
            language_url: '{{ asset('vendor/tinymce/langs/es.js') }}',
            base_url: '{{ asset('vendor/tinymce') }}',
            suffix: '.min',
            height: 420,
            menubar: false,
            plugins: 'lists link image code help wordcount',
            toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | code help',
            content_style: 'body { font-family: "Instrument Sans", sans-serif; font-size: 15px; }',
            branding: false,
            promotion: false,
            relative_urls: false,
            automatic_uploads: true,
            images_upload_handler: function (blobInfo, progress) {
                return new Promise(function (resolve, reject) {
                    var formData = new FormData();
                    formData.append('files[]', blobInfo.blob(), blobInfo.filename());
                    var tokenInput = document.querySelector('input[name="_token"]');
                    formData.append('_token', tokenInput ? tokenInput.value : '');
                    var xhr = new XMLHttpRequest();
                    xhr.open('POST', '{{ url('/admin/medios') }}');
                    xhr.upload.onprogress = function (e) {
                        if (e.lengthComputable && progress) { progress((e.loaded / e.total) * 100); }
                    };
                    xhr.onload = function () {
                        if (xhr.status >= 200 && xhr.status < 300) {
                            try {
                                var json = JSON.parse(xhr.responseText);
                                if (json.location) { resolve(json.location); return; }
                            } catch (e) {}
                            reject('Respuesta de subida inválida');
                        } else if (xhr.status === 422) {
                            try {
                                var j = JSON.parse(xhr.responseText);
                                reject(Object.values(j.errors || {}).flat().join(' ') || 'Archivo rechazado');
                            } catch (e) { reject('Archivo rechazado'); }
                        } else {
                            reject('Error de subida (' + xhr.status + ')');
                        }
                    };
                    xhr.onerror = function () { reject('Error de conexión'); };
                    xhr.send(formData);
                });
            }
        });
    });
</script>
