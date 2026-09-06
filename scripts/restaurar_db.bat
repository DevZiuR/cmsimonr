@echo off
chcp 65001 >nul
setlocal enabledelayedexpansion

echo ======================================================
echo   RESTAURAR BASE DE DATOS - CONTRALORIA MSR
echo ======================================================
echo.

set "MYSQL=C:\xampp\mysql\bin\mysql.exe"
if not exist "%MYSQL%" (
    echo [ERROR] No se encontro mysql.exe en C:\xampp\mysql\bin\
    pause
    exit /b 1
)

set "BACKUP_DIR=%~dp0..\backups"
set "LATEST_SQL="

if "%~1" neq "" (
    if exist "%~1" (
        set "LATEST_SQL=%~1"
    ) else if exist "%BACKUP_DIR%\%~1" (
        set "LATEST_SQL=%BACKUP_DIR%\%~1"
    )
)

if "%LATEST_SQL%"=="" (
    for /f "delims=" %%F in ('dir "%BACKUP_DIR%\contraloria_db_*.sql" /b /o-d 2^>nul') do (
        if not defined LATEST_SQL set "LATEST_SQL=%BACKUP_DIR%\%%F"
    )
)

if "%LATEST_SQL%"=="" (
    echo [ERROR] No se encontraron archivos de respaldo en %BACKUP_DIR%
    pause
    exit /b 1
)

echo Archivo a restaurar:
echo !LATEST_SQL!
echo.
echo ADVERTENCIA: Esta operacion sobreescribira los datos actuales de contraloria_db.
set /p CONFIRM="¿Desea continuar? (S/N): "
if /i "%CONFIRM%" neq "S" (
    echo Operacion cancelada por el usuario.
    pause
    exit /b 0
)

echo.
echo Restaurando base de datos...
"%MYSQL%" --host=localhost --user=root contraloria_db < "!LATEST_SQL!"

if %errorlevel% equ 0 (
    echo.
    echo ✅ Base de datos restaurada con exito desde:
    echo   !LATEST_SQL!
) else (
    echo.
    echo ❌ Ocurrio un error al restaurar la base de datos.
)

echo ======================================================
pause
