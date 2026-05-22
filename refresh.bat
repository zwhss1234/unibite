@echo off
chcp 65001 > nul

echo.
echo  ========================================
echo   UniBite - Refresh Data
echo  ========================================
echo.

netstat -ano | findstr :3307 > nul 2>&1
if errorlevel 1 (
    echo  [!] ERROR: MySQL not running on port 3307.
    echo      Open XAMPP Control Panel and Start MySQL.
    echo.
    pause
    exit /b 1
)

echo  [*] MySQL found on port 3307...
echo  [*] Running seed_data.sql...
echo.

C:\xampp\mysql\bin\mysql.exe -u root -P 3307 --default-character-set=utf8mb4 unibite_db < "%~dp0backend\seed_data.sql"

if errorlevel 1 (
    echo.
    echo  [!] ERROR: Check that unibite_db exists.
    echo      If not, run unibite.sql first.
    echo.
    pause
    exit /b 1
)

echo  [OK] 8 fresh ads added to feed!
echo  [OK] Test user credits reset!
echo.
echo  Open browser: http://localhost/unibite/
echo.
pause
