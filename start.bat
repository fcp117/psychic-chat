@echo off
setlocal
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 (
    echo PHP was not found. Open a new terminal after starting Laravel Herd.
    exit /b 1
)
where npm >nul 2>nul
if errorlevel 1 (
    echo npm was not found. Install Node.js or enable it in Laravel Herd.
    exit /b 1
)
if not exist vendor\autoload.php (
    echo PHP dependencies are missing. Run composer install first.
    exit /b 1
)
if not exist node_modules\.bin\concurrently.cmd (
    echo Frontend dependencies are missing. Run npm ci first.
    exit /b 1
)
if not exist .env (
    echo The .env file is missing. Complete the project environment setup first.
    exit /b 1
)
echo Starting Psychic Chat at http://127.0.0.1:8000 after the frontend build.
echo Keep this terminal open. Press Ctrl+C to stop the services.
echo Close any previously running project services before starting.
call npm start
exit /b %errorlevel%
