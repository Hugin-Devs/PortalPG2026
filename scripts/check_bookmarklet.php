<?php

// Diagnóstico: extrae el JS del bookmarklet y valida reemplazo de PORTAL + sintaxis.
use Illuminate\Contracts\Console\Kernel;

require __DIR__.'/../vendor/autoload.php';
$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$portal = url('/admin/noticias/importar');
echo "URL_PORTAL={$portal}\n";

$blade = file_get_contents(__DIR__.'/../resources/views/admin/news/import.blade.php');
if (! preg_match('/\$js = <<<\'JS\'\r?\n(.*?)\r?\nJS;/s', $blade, $m)) {
    fwrite(STDERR, "heredoc JS no encontrado\n");
    exit(1);
}
$body = $m[1];
echo 'BODY_HAS_TOKEN='.(str_contains($body, '{{PORTAL}}') ? '1' : '0')."\n";

$js = preg_replace('#^\s*//.*$#m', '', $body);
$js = str_replace('{{PORTAL}}', json_encode($portal), preg_replace('/\s*\n\s*/', '', $js));
$check = preg_replace('/^javascript:/', '', $js);

// json_encode escapa / como \/ — comparar con str_contains sobre versión normalizada
$normalized = str_replace('\\/', '/', $js);
echo 'REPLACED_HAS_URL='.(str_contains($normalized, $portal) ? '1' : '0')."\n";
echo 'STILL_HAS_TOKEN='.(str_contains($js, '{{PORTAL}}') ? '1' : '0')."\n";
echo 'HAS_P1='.(str_contains($js, '// P1') ? '1' : '0')."\n";
echo 'HAS_P2='.(str_contains($js, '// P2') ? '1' : '0')."\n";
echo 'LEN='.strlen($js)."\n";

$tmp = tempnam(sys_get_temp_dir(), 'bm').'.js';
file_put_contents($tmp, $check);

$cmd = 'node --check '.escapeshellarg($tmp).' 2>&1';
exec($cmd, $out, $code);
echo "NODE_CHECK={$code}\n";
echo implode("\n", $out)."\n";

$pos = strpos($check, 'var P=');
echo 'FRAG='.($pos !== false ? substr($check, $pos, 80) : 'var P= NOT FOUND')."\n";

@unlink($tmp);
exit($code === 0 ? 0 : 1);
