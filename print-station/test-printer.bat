@echo off
title GGCAST Printer Test
cd /d "%~dp0.."
echo Sending a test slip to POS-80...
echo.
php artisan receipt:test
echo.
pause
