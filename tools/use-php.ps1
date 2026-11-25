param([string]$PhpBinary)

# Solo cambia esta terminal; no modifica PHP, XAMPP ni el PATH del sistema.
$ErrorActionPreference = 'Stop'
if (-not $PhpBinary) {
    $candidates = @(
        (Join-Path $env:USERPROFILE '.config\herd-lite\bin\php.exe'),
        (Get-Command php -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Source)
    ) | Where-Object { $_ -and (Test-Path -LiteralPath $_) }
    foreach ($candidate in $candidates) {
        $version = & $candidate -r 'echo PHP_MAJOR_VERSION,chr(46),PHP_MINOR_VERSION;'
        if ($LASTEXITCODE -eq 0 -and $version -eq '8.5') {
            $PhpBinary = $candidate
            break
        }
    }
}
if (-not $PhpBinary -or -not (Test-Path -LiteralPath $PhpBinary)) {
    throw 'No se encontró PHP 8.5. Indica -PhpBinary con la ruta de tu instalación.'
}
if ((& $PhpBinary -r 'echo PHP_MAJOR_VERSION,chr(46),PHP_MINOR_VERSION;') -ne '8.5') {
    throw 'Se requiere PHP 8.5 para este entorno de desarrollo.'
}
$env:PATH = (Split-Path -Parent $PhpBinary) + [IO.Path]::PathSeparator + $env:PATH
$localIni = Join-Path $PSScriptRoot 'php'
$scanDirectories = @($env:PHP_INI_SCAN_DIR -split [IO.Path]::PathSeparator) | Where-Object { $_ }
if ($scanDirectories -notcontains $localIni) {
    $env:PHP_INI_SCAN_DIR = (@($scanDirectories) + $localIni) -join [IO.Path]::PathSeparator
}
& $PhpBinary -v
Write-Output 'Terminal preparada: PHP 8.5 y configuración local de cargas. Composer y procesos hijos usarán este PHP.'
