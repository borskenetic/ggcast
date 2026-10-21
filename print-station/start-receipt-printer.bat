@echo off
title GGCAST Receipt Printer
color 0A
cd /d "%~dp0.."

echo.
echo  ========================================
echo    GGCAST Receipt Printer
echo  ========================================
echo.
echo  This computer can be on Wi-Fi or Ethernet.
echo  Plug the POS-80 printer into USB.
echo  Do not close this window.
echo.

where php >nul 2>&1
if errorlevel 1 (
    color 0C
    echo  ERROR: PHP was not found.
    echo  Ask IT to install PHP and add it to PATH.
    echo.
    pause
    exit /b 1
)

if not exist "artisan" (
    color 0C
    echo  ERROR: Cannot find the GGCAST app folder.
    echo  Keep this file inside print-station\
    echo.
    pause
    exit /b 1
)

:loop
echo  Starting printer watcher...
echo.
php artisan receipt:watch
echo.
color 0E
echo  The printer app stopped. Restarting in 5 seconds...
echo  Close this window only if you mean to turn printing OFF.
timeout /t 5 /nobreak >nul
color 0A
goto loop
