<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;

/**
 * Utilidades para manejar subidas de archivos de forma segura.
 *
 * El punto crítico es NO usar getClientOriginalExtension(), ya que depende
 * totalmente del cliente y permite bypasses (polyglot XSS / ejecución de
 * código si el servidor mapea la extensión a un handler). En su lugar se usa
 * la extensión adivinada a partir del contenido del archivo.
 */
final class Uploads
{
    /**
     * Devuelve la extensión del archivo basada en su contenido, validando
     * que esté dentro de una lista permitida. Si no es válida, retorna 'bin'
     * como fallback inocuo.
     */
    public static function safeExtension(UploadedFile $file, array $allowed): string
    {
        $ext = strtolower($file->extension() ?: 'bin');

        return in_array($ext, $allowed, true) ? $ext : 'bin';
    }

    /**
     * Guarda el archivo en `uploads/YYYY/MM/` (carpeta por año/mes, como el
     * archivo histórico del portal). Devuelve la ruta relativa al disco given.
     */
    public static function storeDated(UploadedFile $file, string $filename, string $disk = 'public'): string
    {
        return $file->storeAs('uploads/'.date('Y/m'), $filename, $disk);
    }
}
