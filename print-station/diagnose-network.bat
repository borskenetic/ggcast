@echo off
title GGCAST Printer Network Test
cd /d "%~dp0.."
echo.
php print-station\diagnose-network.php
echo.
pause
