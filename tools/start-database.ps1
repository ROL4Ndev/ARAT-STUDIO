param([string]$MariaDbRoot = 'C:\xampp\mysql')

$ErrorActionPreference = 'Stop'
$running = @(Get-Process mysqld,mariadbd -ErrorAction SilentlyContinue)
if ($running.Count -gt 0) {
    Write-Output 'MariaDB ya esta iniciado. No se crea otra instancia.'
    return
}
$socket = New-Object Net.Sockets.TcpClient
try {
    $socket.Connect('127.0.0.1', 3306)
    throw 'El puerto 3306 esta ocupado; no se inicia otro servidor.'
} catch [Net.Sockets.SocketException] {
    # Puerto libre.
} finally {
    $socket.Dispose()
}
$runtime = [IO.Path]::GetFullPath((Join-Path $PSScriptRoot '..\storage\app\private\local-runtime'))
[IO.Directory]::CreateDirectory($runtime) | Out-Null
$executable = Join-Path $MariaDbRoot 'bin\mysqld.exe'
$configuration = Join-Path $MariaDbRoot 'bin\my.ini'
if (-not (Test-Path -LiteralPath $executable) -or -not (Test-Path -LiteralPath $configuration)) {
    throw 'Indica -MariaDbRoot con la carpeta de MariaDB/XAMPP instalada.'
}
Write-Output 'MariaDB local en primer plano. Detener con Ctrl+C en esta terminal.'
# Indices propios: conserva los indices de replicacion de XAMPP, danados en esta instalacion.
& $executable "--defaults-file=$configuration" --standalone --console --skip-slave-start --bind-address=127.0.0.1 "--master-info-file=$runtime\master.info" "--relay-log-info-file=$runtime\relay.info"
