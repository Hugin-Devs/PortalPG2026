<?php

namespace App\Support;

/**
 * Sanitiza HTML almacenado con HTMLPurifier antes de persistirlo y al
 * renderizarlo (defensa en profundidad: si una fila heredada se escribió
 * por SQL crudo, el HTML crudo nunca llega a la vista).
 *
 * Se usa en modelos cuyo contenido se renderiza con {!! !!} (Post, Page)
 * para prevenir XSS almacenado (hallazgo INJ-01 del análisis de vulnerabilidades).
 *
 * La allowlist vive en config/purifier.php (fuente única).
 */
trait SanitizesHtml
{
    /** Memo por instancia del HTML ya purificado en lectura. */
    protected ?string $renderedContentMemo = null;

    protected static function purifyConfig(): array
    {
        return [
            'HTML.Allowed' => (string) config('purifier.allowed'),
            'URI.AllowedSchemes' => (array) config('purifier.allowed_schemes'),
        ];
    }

    protected static function purifyHtml(string $value): string
    {
        // HTMLPurifier aplica URI.AllowedSchemes de forma global. Defensa
        // adicional contra `<a href="data:...">` (hallazgo INJ-01 residual).
        $value = preg_replace(
            '#<a\b[^>]*href=[\'"]?data:[^\'"\s>]*[\'"]?[^>]*>.*?</a>#is',
            '',
            $value
        ) ?? $value;

        return clean($value, static::purifyConfig());
    }

    public function setContentAttribute($value): void
    {
        $this->attributes['content'] = static::purifyHtml((string) $value);
    }

    /**
     * HTML listo para `{!! !!}`: purifica en LECTURA también, para que ni
     * filas anteriores a la mutadora ni escrituras por SQL crudo puedan
     * servir XSS almacenado.
     */
    public function getRenderedContentAttribute(): string
    {
        if ($this->renderedContentMemo !== null) {
            return $this->renderedContentMemo;
        }

        $c = (string) $this->content;

        return $this->renderedContentMemo = preg_match('/<[a-z][^>]*>/i', $c)
            ? static::purifyHtml($c)
            : nl2br(e($c));
    }
}
