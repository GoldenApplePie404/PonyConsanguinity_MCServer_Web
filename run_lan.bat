@echo off
title PHP Server - LAN / Hotspot
echo ========================================================
echo        PHP Server is Starting...
echo        Mode: LAN / Hotspot (Mobile devices can access)
echo        Listen: 0.0.0.0:8080
echo        PHP Path: D:\php8.1\php.exe
echo ========================================================
echo.

echo Local LAN IP addresses (connect to same WiFi/Hotspot):
echo --------------------------------------------------------
for /f "delims=" %%i in ('powershell -NoProfile -Command "Get-NetIPAddress -AddressFamily IPv4 | Where-Object {$_.InterfaceAlias -notmatch 'Loopback' -and $_.IPAddress -notmatch '^169\.254\.'} | ForEach-Object { $_.IPAddress }"') do echo   http://%%i:8080
echo --------------------------------------------------------
echo.
echo Local browser: http://localhost:8080
echo Note: If other devices cannot access, allow php in Windows Firewall.
echo.

start http://localhost:8080

"D:\php8.1\php.exe" -S 0.0.0.0:8080 -t E:/In_development/PC_Web
pause
