@echo off
rem Backup automatico de la base de datos contraloria_db
rem Programe esta tarea en el Programador de tareas de Windows para ejecucion diaria.
powershell -NoProfile -ExecutionPolicy Bypass -File "%~dp0backup_db.ps1"