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
     *
     * D15: sin `img` — las imágenes se gestionan con la portada y la galería
     * de la noticia; lo que se pegue en el texto se elimina al guardar.
     */
    protected static function allowedHtml(): string
    {
        return 'p,br,b,strong,i,em,u,a[href|title],'
            .'h1,h2,h3,h4,h5,h6,ul,ol,li,blockquote,pre,code,'
            .'table,thead,tbody,tr,th,td,span[class],div[class]';
    }

    /**
     * Esquemas de URI permitidos. Sin `data:` desde D15 (ya no hay imágenes
     * inline permitidas; el regex de anclas data: más abajo sigue siendo la
     * defensa principal contra `<a href="data:...">`).
     */
    protected static function allowedUriSchemes(): array
    {
        return [
            'http' => true,
            'https' => true,
            'mailto' => true,
            'tel' => true,
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
