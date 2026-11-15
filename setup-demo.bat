@echo off
setlocal
cd /d "%~dp0"
where php >nul 2>nul
if errorlevel 1 (
    echo PHP 8.2 or newer is required. Install PHP or XAMPP and add PHP to PATH.
    pause
    exit /b 1
)
php setup-demo.php %*
set "setup_exit=%errorlevel%"
if "%~1"=="" pause
exit /b %setup_exit%
