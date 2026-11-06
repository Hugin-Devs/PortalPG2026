<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Http\Response;

class MaintenanceController extends Controller
{
    /**
     * Vista "Sitio en mantenimiento" para /extranet/*.
     *
     * Con la intranet despublicada (scripts/intranet.sh off, ver
     * docs/DOCUMENTACION.md §14.O) el proxy de Nginx no intercepta /extranet/,
     * así que cualquier URL de ese prefijo cae en Laravel: en lugar de un 404
     * se responde 503 con la vista de mantenimiento. Si se reactiva el proxy
     * (intranet.sh on), Nginx captura las peticiones antes que la app y este
     * controlador deja de verse solo.
     */
    public function extranet(): Response
    {
        return response()->view('mantenimiento', [], 503)
            ->header('Retry-After', '3600');
    }
}
