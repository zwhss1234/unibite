@echo off
chcp 65001 > nul
echo.
echo ========================================
echo  UniBite - Quick Database Reset
echo ========================================
echo.
echo Dropping and re-importing unibite_db...
C:\xampp\mysql\bin\mysql.exe -u root -e "DROP DATABASE IF EXISTS unibite_db;"
C:\xampp\mysql\bin\mysql.exe -u root < "%~dp0backend\unibite.sql"
echo.
echo Done! Database reset with fresh seed data.
echo Open: http://localhost/Unibite/frontend/
echo.
pause
