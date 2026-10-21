@echo off
title GGCAST Printer Network Test
cd /d "%~dp0.."
echo Testing whether this PC can reach the website...
echo.
echo 1) HTTPS domain
php -r "echo @file_get_contents('https://ggcast.pantas.org/up') ?: 'FAILED'; echo PHP_EOL;"
echo.
echo 2) HTTP domain
php -r "echo @file_get_contents('http://ggcast.pantas.org/up') ?: 'FAILED'; echo PHP_EOL;"
echo.
echo Open Chrome on this PC and visit https://ggcast.pantas.org
echo If Chrome works but this test fails, PHP is blocked by the network.
echo.
pause
