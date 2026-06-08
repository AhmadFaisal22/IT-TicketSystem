@echo off
title IT HelpDesk - Local Dev

echo ================================================
echo   IT HelpDesk - SEG Solar Manufaktur Indonesia
echo   Local Development
echo ================================================
echo.

:: Get the directory where this .bat file lives
set "ROOT=%~dp0"

echo Starting Laravel Backend (port 8000)...
start "Laravel Backend" cmd /k "cd /d "%ROOT%it-helpdesk-backend" && php artisan serve"

echo Starting Vite Frontend (port 5173)...
start "Vite Frontend" cmd /k "cd /d "%ROOT%it-helpdesk-frontend" && npm run dev"

echo.
echo Both servers starting in separate windows.
echo   Backend  ^>  http://localhost:8000
echo   Frontend ^>  http://localhost:5173
echo.
timeout /t 4 /nobreak >nul
start http://localhost:5173

exit
