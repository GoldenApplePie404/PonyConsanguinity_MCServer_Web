@echo off
title PHP Server + ngrok
echo ========================================================
echo        PHP Server is Starting...
echo        Mode: PHP + ngrok Tunnel
echo        PHP Path: <your_php_path>
echo        ngrok Token: <your_ngrok_token>
echo ========================================================
echo.
echo [1/2] Starting PHP Server...
start /B "PHP Server" "<your_php_path>" -S localhost:8000

timeout /t 3 /nobreak >nul
echo.
echo [2/2] Starting ngrok...
ngrok http 8000 --authtoken=<your_ngrok_token>
pause
