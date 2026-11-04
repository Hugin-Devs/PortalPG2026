<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>Mensaje de contacto #{{ $contact->id }}</title>
    <style>
        body { font-family: Helvetica, Arial, sans-serif; font-size: 12px; color: #212529; }
        h1 { font-size: 17px; color: #273376; margin: 16px 0 8px; border-bottom: 2px solid #273376; padding-bottom: 4px; }
        h2 { font-size: 13px; margin: 0 0 6px; }
        table.datos { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        table.datos td { border: 1px solid #cfd4da; padding: 6px 9px; vertical-align: top; }
        table.datos td.etiqueta { background-color: #eef1f6; width: 30%; font-weight: bold; }
        .estado-nuevo { color: #1b5e20; font-weight: bold; }
        .estado-leido { color: #54595f; }
        .mensaje { border: 1px solid #cfd4da; padding: 10px; white-space: pre-wrap; }
        .pie { margin-top: 20px; font-size: 10px; color: #6c757d; text-align: right; }
    </style>
</head>
<body>
    {{-- Cintillo institucional: franja del navbar (banner.png en "cover") + logo a la izquierda,
         compuesto con GD en ContactController::cintilloPng() y pasado como data URI. --}}
    <img src="{{ $cintillo }}" alt="Gobierno de Distrito Capital" style="width: 100%;">

    <h1>Mensaje de contacto #{{ $contact->id }}</h1>

    <table class="datos">
        <tr>
            <td class="etiqueta">Estado</td>
            <td>
                @if($contact->read_at)
                    <span class="estado-leido">Leído el {{ $contact->read_at->format('d/m/Y H:i') }}</span>
                @else
                    <span class="estado-nuevo">Nuevo (sin leer)</span>
                @endif
            </td>
        </tr>
        <tr>
            <td class="etiqueta">Asunto</td>
            <td>{{ $contact->subject }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Remitente</td>
            <td>{{ $contact->name }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Correo electrónico</td>
            <td>{{ $contact->email }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Fecha de recepción</td>
            <td>{{ $contact->created_at->format('d/m/Y H:i') }}</td>
        </tr>
        <tr>
            <td class="etiqueta">Referencia</td>
            <td>#{{ $contact->id }} — Portal GDC</td>
        </tr>
    </table>

    <h2>Mensaje</h2>
    <div class="mensaje">{!! nl2br(e($contact->message)) !!}</div>

    <div class="pie">
        Generado el {{ now()->format('d/m/Y H:i') }} por {{ $exportedBy->name }} · Portal GDC
    </div>
</body>
</html>
