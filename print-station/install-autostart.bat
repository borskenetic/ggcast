@echo off
title Install GGCAST Receipt Printer Autostart
cd /d "%~dp0"

set "STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "DESKTOP=%USERPROFILE%\Desktop"

if not exist "start-receipt-printer.bat" (
    echo Cannot find start-receipt-printer.bat
    echo Keep this file in the print-station folder.
    pause
    exit /b 1
)

echo Copying to Startup folder...
copy /Y "start-receipt-printer.bat" "%STARTUP%\GGCAST Receipt Printer.bat"
if errorlevel 1 (
    echo Failed to copy to Startup.
    pause
    exit /b 1
)

echo Copying to Desktop...
copy /Y "start-receipt-printer.bat" "%DESKTOP%\GGCAST Receipt Printer.bat"
if errorlevel 1 (
    echo Failed to copy to Desktop.
    pause
    exit /b 1
)

echo.
echo Done.
echo - It will start when this Windows user logs in.
echo - Use the Desktop icon "GGCAST Receipt Printer" to start it by hand.
echo.
pause
