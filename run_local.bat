@echo off
title PHP Server - Local Only
echo ========================================================
echo        PHP Server is Starting...
echo        Mode: Local Development Only
echo        Address: http://localhost:8000
echo        PHP Path: D:\php8.1\php.exe
echo ========================================================
echo.

start http://localhost:8000

"D:\php8.1\php.exe" -S localhost:8000
pause
