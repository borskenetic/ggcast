@echo off
title Remove GGCAST Receipt Printer Autostart
del /q "%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup\GGCAST Receipt Printer.lnk" 2>nul
del /q "%USERPROFILE%\Desktop\GGCAST Receipt Printer.lnk" 2>nul
echo Autostart and desktop shortcut removed.
pause
