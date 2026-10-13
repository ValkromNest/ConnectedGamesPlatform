@echo off
setlocal
set "PROJECT_DIR=%~dp0"
where g++ >nul 2>nul
if errorlevel 1 (
    echo g++ non trovato. Installa MinGW-w64 oppure aggiungi la cartella bin al PATH.
    exit /b 1
)

g++ -std=c++17 -O2 -Wall -Wextra "%PROJECT_DIR%src\main.cpp" -o "%PROJECT_DIR%device-simulator.exe" -lwinhttp
if errorlevel 1 exit /b %errorlevel%

"%PROJECT_DIR%device-simulator.exe" %*
exit /b %errorlevel%
