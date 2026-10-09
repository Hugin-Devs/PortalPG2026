<?php

namespace App\Support;

/**
 * Sanitiza HTML almacenado con HTMLPurifier antes de persistirlo.
 *
 * Se usa en modelos cuyo contenido se renderiza con {!! !!} (Post, Page)
 * para prevenir XSS almacenado (hallazgo INJ-01 del análisis de vulnerabilidades).
 */
trait SanitizesHtml
{
    /**
     * Etiquetas HTML permitidas tras sanitización (alineado con TinyMCE).
     */
    protected static function allowedHtml(): string
    {
        return 'p,br,b,strong,i,em,u,a[href|title],img[src|alt|width|height],'
            .'h1,h2,h3,h4,h5,h6,ul,ol,li,blockquote,pre,code,'
            .'table,thead,tbody,tr,th,td,span[class],div[class]';
    }

    /**
     * Esquemas de URI permitidos. Se incluye `data` para que TinyMCE pueda
     * pegar imágenes inline como `data:image/png;base64,...`.
     */
    protected static function allowedUriSchemes(): array
    {
        return [
            'http' => true,
            'https' => true,
            'mailto' => true,
            'tel' => true,
            'data' => true,
        ];
    }

    public function setContentAttribute($value): void
    {
        // HTMLPurifier aplica URI.AllowedSchemes de forma global. Permitiendo
        // `data:` para imágenes inline de TinyMCE (`<img src="data:...">`)
        // también lo habilitaría en `<a href="data:...">`. Eliminamos el
        // ancla completa antes de la sanitización (hallazgo INJ-01 residual)
        // mientras conservamos las imágenes inline.
        $value = preg_replace(
            '#<a\b[^>]*href=[\'"]?data:[^\'"\s>]*[\'"]?[^>]*>.*?</a>#is',
            '',
            $value
        ) ?? $value;

        $this->attributes['content'] = clean($value, [
            'HTML.Allowed' => static::allowedHtml(),
            'URI.AllowedSchemes' => static::allowedUriSchemes(),
        ]);
    }
}
