<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Support\Audit;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditController extends Controller
{
    public function index(Request $request)
    {
        $filters = $this->validateFilters($request);

        $logs = $this->filteredQuery($filters)
            ->with('user')
            ->latest()
            ->paginate(50)
            ->withQueryString();

        $actions = ActivityLog::select('action')->distinct()->orderBy('action')->pluck('action');
        $users = ActivityLog::with('user')->whereNotNull('user_id')->get()->pluck('user')->unique('id')->filter();

        return view('admin.audit.index', compact('logs', 'filters', 'actions', 'users'));
    }

    /**
     * Exporta los registros filtrados como CSV (permiso audit.export).
     * Las celdas que empiezan por un carácter de fórmula (= + - @ o tab)
     * se neutralizan con un prefijo apostrofe (CWE-1236, CSV injection):
     * los títulos y descripciones contienen contenido de usuario.
     */
    public function export(Request $request): StreamedResponse
    {
        $filters = $this->validateFilters($request);

        $count = $this->filteredQuery($filters)->count();

        Audit::log('audit.exported', ActivityLog::class, null, "Exportación CSV de auditoría: {$count} registro(s)", [
            'filters' => $filters,
            'count' => $count,
        ]);

        $filename = 'auditoria-'.now()->format('Ymd-His').'.csv';

        return new StreamedResponse(function () use ($filters) {
            $out = fopen('php://output', 'w');
            // BOM UTF-8 para que Excel reconozca los acentos.
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Fecha', 'Usuario', 'Email', 'Rol', 'Acción', 'Entidad', 'ID', 'IP', 'Descripción', 'Metadata', 'User-Agent'], ';');

            $this->filteredQuery($filters)
                ->with('user')
                ->orderByDesc('created_at')
                ->chunk(500, function ($logs) use ($out) {
                    foreach ($logs as $log) {
                        fputcsv($out, [
                            $this->csvSafe($log->created_at?->format('d/m/Y H:i:s')),
                            $this->csvSafe($log->user->name),
                            $this->csvSafe($log->user->email),
                            $this->csvSafe($log->role),
                            $this->csvSafe($log->action),
                            $this->csvSafe($log->entity_type ? class_basename($log->entity_type) : ''),
                            $this->csvSafe($log->entity_id),
                            $this->csvSafe($log->ip_address),
                            $this->csvSafe($log->description),
                            $this->csvSafe($log->metadata ? json_encode($log->metadata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) : ''),
                            $this->csvSafe($log->user_agent),
                        ], ';');
                    }
                });

            fclose($out);
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
        ]);
    }

    /**
     * Neutraliza CSV injection: celdas que empiezan por = + - @ o tab
     * llevan un apostrofe delante para que las hojas de cálculo las traten
     * como texto (CWE-1236). El resto se devuelve intacto.
     */
    private function csvSafe(mixed $value): string
    {
        $value = (string) ($value ?? '');

        return preg_match('/^[=+\-@\t\r]/', $value) === 1 ? "'".$value : $value;
    }

    /**
     * Allowlist de parámetros de filtro (anti-mass-assignment de query).
     */
    private function validateFilters(Request $request): array
    {
        return $request->validate([
            'action' => ['nullable', 'string', 'max:64'],
            'user_id' => ['nullable', 'integer', 'min:1'],
            'role' => ['nullable', 'string', 'max:32'],
            'q' => ['nullable', 'string', 'max:128'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date'],
        ], [], [
            'action' => 'acción',
            'user_id' => 'usuario',
            'role' => 'rol',
            'q' => 'búsqueda',
            'date_from' => 'desde',
            'date_to' => 'hasta',
        ]);
    }

    private function filteredQuery(array $filters)
    {
        return ActivityLog::query()
            ->when($filters['action'] ?? null, fn ($q, $action) => $q->where('action', $action))
            ->when($filters['user_id'] ?? null, fn ($q, $userId) => $q->where('user_id', $userId))
            ->when($filters['role'] ?? null, fn ($q, $role) => $q->where('role', $role))
            ->when($filters['q'] ?? null, function ($q, $search) {
                $q->where(function ($sub) use ($search) {
                    $sub->where('description', 'like', "%{$search}%")
                        ->orWhere('entity_type', 'like', "%{$search}%")
                        ->orWhere('ip_address', 'like', "%{$search}%");
                });
            })
            ->when($filters['date_from'] ?? null, fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($filters['date_to'] ?? null, fn ($q, $to) => $q->whereDate('created_at', '<=', $to));
    }
}
