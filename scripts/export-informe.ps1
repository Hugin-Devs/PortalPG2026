<#
.SYNOPSIS
  Exporta docs/informe-estado-actual.html a PDF usando Microsoft Edge headless.

.DESCRIPTION
  No requiere dependencias adicionales: usa el Edge ya instalado en Windows.
  El PDF generado NO se versiona: es el artefacto de entrega local.
  Equivalente manual: abrir el HTML en el navegador → Imprimir → Guardar como PDF
  (papel tamaño CARTA).

.EXAMPLE
  .\scripts\export-informe.ps1
#>
$ErrorActionPreference = 'Stop'

$root   = Split-Path -Parent $PSScriptRoot
$html   = Join-Path $root 'docs\informe-estado-actual.html'
$pdf    = Join-Path $root 'docs\informe-estado-actual.pdf'

if (-not (Test-Path $html)) {
    Write-Error "No se encontró $html"
    exit 1
}

$candidates = @(
    (Join-Path ${env:ProgramFiles(x86)} 'Microsoft\Edge\Application\msedge.exe'),
    (Join-Path $env:ProgramFiles 'Microsoft\Edge\Application\msedge.exe'),
    (Join-Path $env:LOCALAPPDATA 'Microsoft\Edge\Application\msedge.exe')
)
$edge = $candidates | Where-Object { Test-Path $_ } | Select-Object -First 1

if (-not $edge) {
    Write-Error 'No se encontró Microsoft Edge. Exporta manualmente: abrir el HTML → Imprimir → Guardar como PDF (carta).'
    exit 1
}

if (Test-Path $pdf) {
    Remove-Item $pdf -Force
}

# file:/// con barras inclinadas; Edge en modo headless imprime con las
# reglas @page del documento (tamaño carta, márgenes 2 cm).
$uri = 'file:///' + ($html -replace '\\', '/')

# Edge escribe avisos internos a stderr (inofensivos); con EAP Stop,
# PowerShell los elevaría a error, así que se desactiva durante la llamada.
$prevEap = $ErrorActionPreference
$ErrorActionPreference = 'Continue'
& $edge --headless=new --disable-gpu --no-pdf-header-footer --print-to-pdf="$pdf" $uri 2>&1 | Out-Null
$ErrorActionPreference = $prevEap

# Edge headless a veces devuelve antes de cerrar; esperar a que exista el archivo.
$deadline = (Get-Date).AddSeconds(30)
while (-not (Test-Path $pdf) -and (Get-Date) -lt $deadline) {
    Start-Sleep -Milliseconds 500
}

if (-not (Test-Path $pdf)) {
    Write-Error 'Edge no generó el PDF. Prueba abrir el HTML en el navegador e imprimir manualmente (carta).'
    exit 1
}

$kb = [math]::Round((Get-Item $pdf).Length / 1KB)
Write-Host "PDF generado: $pdf ($kb KB)" -ForegroundColor Green
