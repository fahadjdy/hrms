@echo off
rem Starts the HRMS for local development on this machine:
rem   - puts PHP 8.4 (Laragon) first on PATH; the system PHP 8.2 is too old
rem   - starts the XAMPP MariaDB server if nothing is listening on port 3306
rem   - runs the web server, queue worker and Vite together (composer run dev)

set "PATH=C:\laragon\bin\php\php-8.4.20;%PATH%"

netstat -an | findstr /C:":3306 " | findstr LISTENING >nul
if errorlevel 1 (
    echo Starting MariaDB...
    start "" /B "C:\xampp\mysql\bin\mysqld.exe" --defaults-file="C:\xampp\mysql\bin\my.ini" --standalone
    timeout /t 12 /nobreak >nul
)

cd /d "%~dp0"
composer run dev
