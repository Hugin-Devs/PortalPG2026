<?php

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

/**
 * Importación asistida de posts de Instagram (opción C del plan F0).
 *
 * - normalizePostUrl: valida/canoniza URLs de posts (/p/, /reel/, /tv/).
 * - fetchMeta: intento best-effort de og:* server-side (puede fallar por
 *   login wall 2026; no es el flujo principal — el bookmarklet lo es).
 * - downloadImage: descarga solo de hosts allowlist de CDN de Meta (SSRF).
 *
 * No usa oEmbed de Meta: sus términos prohíben extraer/persistir contenido
 * para un CMS (solo embed). Ver docs/PLAN-IMPORT-IG.md §0.
 */
final class InstagramImport
{
    /** Sufijos de host permitidos para imágenes de portada (CDN Meta). */
    private const IMAGE_HOST_SUFFIXES = [
        '.cdninstagram.com',
        '.fbcdn.net',
    ];

    private const POST_PATH_REGEX = '#^/(p|reel|tv)/([A-Za-z0-9_-]+)/?$#';

    private const ALLOWED_POST_HOSTS = [
        'instagram.com',
        'www.instagram.com',
        'm.instagram.com',
        'instagr.am',
        'www.instagr.am',
    ];

    /**
     * Canoniza una URL de post de Instagram o devuelve null si no lo es.
     * Solo http(s), host de IG y path /p/|/reel/|/tv/. Sin query ni fragment.
     */
    public static function normalizePostUrl(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return null;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'], $parts['path'])) {
            return null;
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            return null;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }

        $host = strtolower($parts['host']);

        if (! in_array($host, self::ALLOWED_POST_HOSTS, true)) {
            return null;
        }

        if (! preg_match(self::POST_PATH_REGEX, $parts['path'], $m)) {
            return null;
        }

        return 'https://www.instagram.com/'.$m[1].'/'.$m[2].'/';
    }

    /**
     * ¿Es una URL de imagen descargable del CDN de Meta? https + sufijo
     * allowlist + sin credenciales en la URL. Bloquea IPs y hosts arbitrarios.
     */
    public static function isAllowedImageUrl(string $url): bool
    {
        $url = trim($url);

        if ($url === '' || filter_var($url, FILTER_VALIDATE_URL) === false) {
            return false;
        }

        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return false;
        }

        if (strtolower($parts['scheme']) !== 'https') {
            return false;
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return false;
        }

        $host = strtolower($parts['host']);

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return false;
        }

        foreach (self::IMAGE_HOST_SUFFIXES as $suffix) {
            if (str_ends_with($host, $suffix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Intenta leer og:title / og:description / og:image / fecha de
     * publicación de la página pública del post. Devuelve null si la URL
     * no es válida o IG sirve login wall sin metadatos.
     *
     * @return array{title: ?string, content: ?string, image_url: ?string, published_at: ?string}|null
     */
    public function fetchMeta(string $url): ?array
    {
        $canonical = self::normalizePostUrl($url);

        if ($canonical === null) {
            return null;
        }

        try {
            $response = Http::timeout(8)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/126.0 Safari/537.36',
                    'Accept-Language' => 'es-VE,es;q=0.9',
                ])
                ->withOptions([
                    'allow_redirects' => ['max' => 2],
                ])
                ->get($canonical);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $html = $response->body();
        $title = self::ogContent($html, 'og:title');
        $description = self::ogContent($html, 'og:description');
        $image = self::ogContent($html, 'og:image');
        $publishedAt = self::extractPublishedAt($html);

        if ($title === null && $description === null) {
            // Login wall o shell vacío: sin metadatos utilizables.
            return null;
        }

        return [
            'title' => $title,
            'content' => $description,
            'image_url' => $image,
            'published_at' => $publishedAt,
        ];
    }

    /**
     * Fecha de publicación del post en el HTML (best effort):
     * `<time datetime>` → `article:published_time` → `taken_at_timestamp` (unix).
     */
    private static function extractPublishedAt(string $html): ?string
    {
        if (preg_match('#<time[^>]+datetime=["\']([^"\']+)["\']#i', $html, $m)) {
            return $m[1];
        }

        $ap = self::ogContent($html, 'article:published_time');

        if ($ap !== null && $ap !== '') {
            return $ap;
        }

        if (preg_match('/"taken_at_timestamp"\s*:\s*(\d{10})/', $html, $m)) {
            return date('c', (int) $m[1]);
        }

        return null;
    }

    /**
     * Descarga una imagen del CDN permitido. Devuelve null en cualquier
     * fallo (host, timeout, no-imagen, >10 MB). Sin redirects a hosts
     * fuera de allowlist.
     *
     * @return array{binary: string, mime: string, ext: string}|null
     */
    public function downloadImage(string $url): ?array
    {
        if (! self::isAllowedImageUrl($url)) {
            return null;
        }

        try {
            $response = Http::timeout(10)
                ->withOptions([
                    'allow_redirects' => [
                        'max' => 2,
                        'on_redirect' => fn ($request, $response, $uri) => self::isAllowedImageUrl((string) $uri),
                    ],
                ])
                ->get($url);
        } catch (ConnectionException) {
            return null;
        }

        if (! $response->successful()) {
            return null;
        }

        $binary = $response->body();

        if ($binary === '' || strlen($binary) > 10 * 1024 * 1024) {
            return null;
        }

        $info = @getimagesizefromstring($binary);

        if ($info === false) {
            return null;
        }

        $ext = match ($info['mime'] ?? '') {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            default => null,
        };

        if ($ext === null) {
            return null;
        }

        return [
            'binary' => $binary,
            'mime' => $info['mime'],
            'ext' => $ext,
        ];
    }

    private static function ogContent(string $html, string $property): ?string
    {
        $quoted = preg_quote($property, '#');

        if (preg_match('#<meta[^>]+property=["\']'.$quoted.'["\'][^>]+content=["\']([^"\']*)["\']#i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }

        if (preg_match('#<meta[^>]+content=["\']([^"\']*)["\'][^>]+property=["\']'.$quoted.'["\']#i', $html, $m)) {
            return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5);
        }

        return null;
    }
}
