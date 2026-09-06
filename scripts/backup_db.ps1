$ErrorActionPreference = 'Stop'

# Configuración
$root = Split-Path -Parent $PSScriptRoot
$backupDir = Join-Path $root 'backups'
$dbHost = 'localhost'
$dbUser = 'root'
$dbPass = ''
$dbName = 'contraloria_db'
$keepLast = 30

# Ubicar mysqldump
$mysqldump = 'C:\xampp\mysql\bin\mysqldump.exe'
if (-not (Test-Path -LiteralPath $mysqldump)) {
    $cmd = Get-Command mysqldump -ErrorAction SilentlyContinue
    if ($cmd) { $mysqldump = $cmd.Source }
    else { Write-Error 'mysqldump no encontrado. Ajuste la ruta en scripts/backup_db.ps1.'; exit 1 }
}

New-Item -ItemType Directory -Force -Path $backupDir | Out-Null

$stamp = Get-Date -Format 'yyyyMMdd_HHmmss'
$file = Join-Path $backupDir "contraloria_db_$stamp.sql"
$log  = Join-Path $backupDir 'backup.log'

$dumpArgs = @()
if ($dbPass -ne '') { $dumpArgs += '--password=' + $dbPass }
$dumpArgs += '--host=' + $dbHost
$dumpArgs += '--user=' + $dbUser
$dumpArgs += '--routines'
$dumpArgs += '--single-transaction'
$dumpArgs += '--result-file=' + $file
$dumpArgs += $dbName

& $mysqldump @dumpArgs 2>&1 | Out-Null
if ($LASTEXITCODE -ne 0) {
    "[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] ERROR backup fallido (exit $LASTEXITCODE)" | Add-Content -Path $log
    Write-Error 'mysqldump fallo. Revise backup.log.'
    exit 1
}

# Rotación: conservar solo los últimos $keepLast
Get-ChildItem -Path $backupDir -Filter 'contraloria_db_*.sql' | Sort-Object LastWriteTime -Descending | Select-Object -Skip $keepLast | Remove-Item -Force -ErrorAction SilentlyContinue

$size = [math]::Round((Get-Item -LiteralPath $file).Length / 1KB, 1)
"[$(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')] OK $file ($size KB)" | Add-Content -Path $log
Write-Output "Backup creado: $file ($size KB)"