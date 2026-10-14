<textarea name="content" id="content" class="form-control" rows="10">{{ $value ?? '' }}</textarea>
<script nonce="{{ request()->attributes->get('csp_nonce') }}" src="{{ asset('vendor/tinymce/tinymce.min.js') }}"></script>
<script nonce="{{ request()->attributes->get('csp_nonce') }}">
    document.addEventListener('DOMContentLoaded', function () {
        tinymce.init({
            selector: '#content',
            license_key: 'gpl',
            language: 'es',
            language_url: '{{ asset('vendor/tinymce/langs/es.js') }}',
            base_url: '{{ asset('vendor/tinymce') }}',
            suffix: '.min',
            height: 420,
            menubar: false,
            plugins: 'lists link code help wordcount',
            toolbar: 'undo redo | styles | bold italic underline | alignleft aligncenter alignright | bullist numlist | link | code help',
            content_style: 'body { font-family: "Instrument Sans", sans-serif; font-size: 15px; }',
            branding: false,
            promotion: false,
            relative_urls: false
        });
        var contentField = document.getElementById('content');
        var form = contentField.closest('form');
        if (form) {
            form.addEventListener('submit', function (e) {
                tinymce.triggerSave();
                var v = (contentField.value || '').replace(/\s|&nbsp;/g, '');
                if (!v || v === '<p></p>' || v === '<p><br></p>' || v === '<p><br/></p>') {
                    e.preventDefault();
                    alert('El campo Contenido es obligatorio.');
                }
            });
        }
    });
</script>
