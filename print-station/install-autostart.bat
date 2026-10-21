@echo off
title Install GGCAST Receipt Printer Autostart
cd /d "%~dp0"

set "STARTUP=%APPDATA%\Microsoft\Windows\Start Menu\Programs\Startup"
set "DESKTOP=%USERPROFILE%\Desktop"
set "TARGET=%~dp0start-receipt-printer.bat"

echo Creating Startup shortcut...
powershell -NoProfile -Command ^
  "$s = (New-Object -ComObject WScript.Shell).CreateShortcut('%STARTUP%\GGCAST Receipt Printer.lnk'); ^
   $s.TargetPath = '%TARGET%'; ^
   $s.WorkingDirectory = '%~dp0..'; ^
   $s.WindowStyle = 1; ^
   $s.Description = 'GGCAST self-checkout receipt printer'; ^
   $s.Save()"

echo Creating Desktop shortcut...
powershell -NoProfile -Command ^
  "$s = (New-Object -ComObject WScript.Shell).CreateShortcut('%DESKTOP%\GGCAST Receipt Printer.lnk'); ^
   $s.TargetPath = '%TARGET%'; ^
   $s.WorkingDirectory = '%~dp0..'; ^
   $s.WindowStyle = 1; ^
   $s.Description = 'GGCAST self-checkout receipt printer'; ^
   $s.Save()"

echo.
echo Done.
echo - It will start when this Windows user logs in.
echo - A "GGCAST Receipt Printer" icon is on the Desktop if you need to start it by hand.
echo.
pause
