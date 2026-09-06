@echo off
chcp 65001 >nul
echo ======================================================
echo   RESPALDO DE BASE DE DATOS - CONTRALORIA MSR
echo ======================================================
echo.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0backup_db.ps1"
if %errorlevel% equ 0 (
    echo.
    echo Respaldo completado con exito.
) else (
    echo.
    echo Ocurrio un error durante el respaldo.
)
echo ======================================================
if "%1" neq "--silent" pause