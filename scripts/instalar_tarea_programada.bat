@echo off
chcp 65001 >nul
echo ======================================================
echo   INSTALADOR DE TAREA PROGRAMADA - RESPALDO DIARIO
echo ======================================================
echo.

powershell -NoProfile -ExecutionPolicy Bypass -Command ^
  "$action = New-ScheduledTaskAction -Execute 'C:\xampp\htdocs\sistema\scripts\backup_db.bat' -Argument '--silent';" ^
  "$trigger = New-ScheduledTaskTrigger -Daily -At 11:00PM;" ^
  "Register-ScheduledTask -TaskName 'Backup_Sistema_Contraloria' -Action $action -Trigger $trigger -Description 'Respaldo automatico diario de contraloria_db' -Force;"

if %errorlevel% equ 0 (
    echo.
    echo ✅ Tarea programada instalada con exito.
    echo   Se ejecutara automaticamente todos los dias a las 11:00 PM.
) else (
    echo.
    echo ⚠️ Si fallo por permisos, ejecute este archivo haciendo clic derecho:
    echo   'Ejecutar como administrador'
)

echo ======================================================
pause
