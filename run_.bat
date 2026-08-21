@echo off
title Server Launcher

:menu
echo ========================================================
echo         Server Launcher
echo ========================================================
echo [1] Local Development (localhost:8000)
echo [2] Public Access (ngrok Tunnel)
echo [3] LAN / Hotspot Mode (0.0.0.0:8080)
echo [4] Check ^& Init Data Directory
echo [0] Exit
echo ========================================================

set /p choice=Select mode (0-4):

if "%choice%"=="1" goto local
if "%choice%"=="2" goto ngrok
if "%choice%"=="3" goto lan
if "%choice%"=="4" goto checkdata
if "%choice%"=="0" exit

echo Invalid choice, please try again
pause
goto menu

:local
echo ========================================================
echo         Starting Local Development...
echo ========================================================
echo.
echo [1/2] Starting PHP server...
start "PHP Server" "D:\php8.1\php.exe" -S localhost:8000
echo.
echo [2/2] Opening browser...
start http://localhost:8000
echo.
echo Local dev server started!
echo URL: http://localhost:8000
echo.
echo Press any key to exit...
pause >nul
exit

:ngrok
echo ========================================================
echo         Starting Public Access (ngrok)...
echo ========================================================
echo.
echo [1/3] Starting PHP server...
start /B "PHP Server" "D:\php8.1\php.exe" -S localhost:8000
echo.
echo [2/3] Waiting for server to start...
timeout /t 3 /nobreak >nul
echo.
echo [3/3] Starting ngrok tunnel...
echo Generating public URL, please wait...
echo.
ngrok http 8000 --authtoken=<your_ngrok_auth_token>

:lan
echo ========================================================
echo         Starting LAN / Hotspot Mode...
echo ========================================================
echo.
echo Mobile/other devices: connect to this PC's hotspot or same WiFi.
echo Local LAN IPs will be listed below - open one in your mobile browser.
echo.
call "E:\In_development\PC_Web\run_lan.bat"
exit

:checkdata
echo ========================================================
echo         Checking Data Directory...
echo ========================================================
echo.
echo This will verify data/ completeness and auto-fill
echo any missing files from data-init/ templates.
echo Existing data will NOT be overwritten.
echo.
"D:\php8.1\php.exe" "E:\In_development\PC_Web\tools\check_data_init.php"
echo.
pause
exit
