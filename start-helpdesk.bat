@echo off
title IT HelpDesk - Start Servers

echo ================================================
echo   IT HelpDesk - SEG Solar Manufaktur Indonesia
echo ================================================
echo.
echo Starting Laravel Backend (port 8000)...
start "Laravel Backend" cmd /k "cd /d "C:\Users\LENOVO\Agentic Workflow\it-helpdesk-backend" && php artisan serve"

echo Starting Vite Frontend (port 5173)...
start "Vite Frontend" cmd /k "cd /d "C:\Users\LENOVO\Agentic Workflow\it-helpdesk-frontend" && npm run dev"

echo.
echo Both servers are starting in separate windows.
echo.
echo   Backend  ^>  http://localhost:8000
echo   Frontend ^>  http://localhost:5173
echo.
timeout /t 3 /nobreak >nul
start http://localhost:5173

exit
