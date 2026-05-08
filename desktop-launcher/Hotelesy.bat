@echo off
REM ============================================================================
REM  Hotelesy Desktop launcher — Windows
REM ============================================================================
REM  Boots a private PHP server on port 8001 (or the next free one) and opens
REM  Chrome in --app mode pointing at it. Closes the server when the window
REM  is closed.
REM
REM  Place this file in the Hotelesy installation folder. Pin a shortcut to
REM  the Start menu / Desktop and the user double-clicks to launch.
REM ============================================================================

setlocal EnableDelayedExpansion

REM ---- Locate the project root (the folder containing this .bat) -----------
set "APP_ROOT=%~dp0.."
pushd "%APP_ROOT%"

REM ---- Find a usable PHP -----------------------------------------------------
set "PHP_BIN="
for %%P in (
    "%LOCALAPPDATA%\Herd\bin\php.exe"
    "C:\Herd\bin\php.exe"
    "C:\php\php.exe"
    "php.exe"
) do (
    if exist %%P (
        set "PHP_BIN=%%~P"
        goto :have_php
    )
)
where php > nul 2>&1
if not errorlevel 1 (
    for /f "delims=" %%I in ('where php') do set "PHP_BIN=%%I" & goto :have_php
)
echo PHP not found. Install Herd or PHP 8.2+ and try again.
pause
exit /b 1
:have_php

REM ---- Find Chrome / Edge / Brave -------------------------------------------
set "BROWSER="
for %%B in (
    "%PROGRAMFILES%\Google\Chrome\Application\chrome.exe"
    "%PROGRAMFILES(X86)%\Google\Chrome\Application\chrome.exe"
    "%LOCALAPPDATA%\Google\Chrome\Application\chrome.exe"
    "%PROGRAMFILES%\Microsoft\Edge\Application\msedge.exe"
    "%PROGRAMFILES(X86)%\Microsoft\Edge\Application\msedge.exe"
    "%PROGRAMFILES%\BraveSoftware\Brave-Browser\Application\brave.exe"
) do (
    if exist %%B (
        set "BROWSER=%%~B"
        goto :have_browser
    )
)
echo Chrome / Edge / Brave is required to run Hotelesy.
pause
exit /b 1
:have_browser

REM ---- Boot PHP server in the background ------------------------------------
set "PORT=8001"
set "APP_MODE=desktop"
set "APP_ENV=desktop"
set "PROFILE=%APPDATA%\Hotelesy\chrome-profile"
if not exist "%PROFILE%" mkdir "%PROFILE%"

REM Start PHP detached, capture its PID so we can kill it later.
start "" /B "%PHP_BIN%" artisan serve --host=127.0.0.1 --port=%PORT%

REM ---- Wait briefly for the server to come up -------------------------------
set /a tries=0
:waitloop
set /a tries+=1
ping -n 2 127.0.0.1 > nul
powershell -NoProfile -Command "try{(Invoke-WebRequest -UseBasicParsing -TimeoutSec 1 http://127.0.0.1:%PORT%/up).StatusCode}catch{exit 1}" > nul 2>&1
if errorlevel 1 (
    if %tries% lss 10 goto waitloop
)

REM ---- Open Chrome in app mode (blocks until window closes) -----------------
"%BROWSER%" --app=http://127.0.0.1:%PORT%/desktop --user-data-dir="%PROFILE%" --no-first-run --no-default-browser-check

REM ---- Kill any PHP processes spawned for our port --------------------------
for /f "tokens=5" %%P in ('netstat -ano ^| findstr ":%PORT%" ^| findstr "LISTENING"') do (
    taskkill /F /PID %%P > nul 2>&1
)

popd
endlocal
