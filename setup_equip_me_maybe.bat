@echo off
setlocal EnableExtensions

REM Equip Me Maybe - Windows setup helper
REM Run this file from the root of the cloned repository.
REM Required beforehand: PHP 8.2+, Composer, Git, Node.js/npm.
REM MySQL must be installed separately (or use another supported database).

set "SOURCE_DIR=%~dp0"
set "APP_DIR=%SOURCE_DIR%equip-me-maybe-app"

echo.
echo ==========================================
echo   EQUIP ME MAYBE - LARAVEL SETUP
echo ==========================================
echo.

REM Check required commands
where php >nul 2>nul
if errorlevel 1 (
  echo ERROR: PHP was not found. Install PHP 8.2 or newer and add it to PATH.
  goto :fail
)

where composer >nul 2>nul
if errorlevel 1 (
  echo ERROR: Composer was not found. Install Composer and add it to PATH.
  goto :fail
)

where node >nul 2>nul
if errorlevel 1 (
  echo ERROR: Node.js was not found. Install the Node.js LTS version.
  goto :fail
)

where npm >nul 2>nul
if errorlevel 1 (
  echo ERROR: npm was not found. Reinstall Node.js LTS with npm enabled.
  goto :fail
)

echo Detected versions:
php -v
composer --version
node --version
npm --version
echo.

if exist "%APP_DIR%\artisan" (
  echo A Laravel project already exists at:
  echo "%APP_DIR%"
  echo.
  echo To avoid overwriting existing work, this script will stop.
  echo If you intentionally want a fresh installation, rename or move that folder first.
  goto :fail
)

echo Step 1 of 6: Creating a fresh Laravel 12 project...
composer create-project laravel/laravel "%APP_DIR%" "12.*"
if errorlevel 1 goto :fail

cd /d "%APP_DIR%"

echo.
echo Step 2 of 6: Installing Laravel Breeze...
composer require laravel/breeze --dev
if errorlevel 1 goto :fail

echo.
echo Step 3 of 6: Generating Breeze Blade authentication and Sanctum API support...
php artisan breeze:install blade
if errorlevel 1 goto :fail

php artisan install:api
if errorlevel 1 goto :fail

echo.
echo Step 4 of 6: Copying the Equip Me Maybe backend overlay...
echo Source: "%SOURCE_DIR%"
echo Target: "%APP_DIR%"
echo.

if not exist "%SOURCE_DIR%app\" (
  echo ERROR: The app folder was not found beside this setup file.
  echo Put setup_equip_me_maybe.bat in the root of the cloned backend repository.
  goto :fail
)

for %%D in (app database routes tests) do (
  if exist "%SOURCE_DIR%%%D\" (
    robocopy "%SOURCE_DIR%%%D" "%APP_DIR%\%%D" /E /R:2 /W:2 /NFL /NDL /NJH /NJS
    if errorlevel 8 (
      echo ERROR: Failed while copying %%D.
      goto :fail
    )
  )
)

if exist "%SOURCE_DIR%bootstrap\app.php" (
  copy /Y "%SOURCE_DIR%bootstrap\app.php" "%APP_DIR%\bootstrap\app.php" >nul
  if errorlevel 1 goto :fail
) else (
  echo ERROR: bootstrap\app.php was not found in the source repository.
  goto :fail
)

echo.
echo Step 5 of 6: Installing JavaScript dependencies and building assets...
call npm install
if errorlevel 1 goto :fail

call npm run build
if errorlevel 1 goto :fail

echo.
echo Step 6 of 6: Setup files are ready.
echo.
echo NEXT STEPS:
echo 1. Open "%APP_DIR%\.env" and configure your database connection.
echo 2. Create the database in MySQL, for example: equip_me_maybe
echo 3. In a terminal, run:
echo.
echo    cd /d "%APP_DIR%"
echo    php artisan migrate:fresh --seed
echo    php artisan storage:link
echo    php artisan test
echo.
echo 4. Start the app:
echo    php artisan serve
echo.
echo 5. In a second terminal, run "npm run dev" if you are actively editing frontend assets.
echo.
echo IMPORTANT: migrate:fresh deletes existing tables in the configured database.
echo Use it only with a disposable project database.
echo Blade feature pages listed in README.md still need to be created.
echo.
echo Demo password from the seeder: password
echo Demo emails: head1@equip.test, manager1@equip.test,
echo               student1@equip.test, faculty1@equip.test,
echo               auditor1@equip.test
echo.
pause
exit /b 0

:fail
echo.
echo SETUP STOPPED. Read the error above, fix it, then run this file again.
echo No existing Laravel project folder was intentionally overwritten.
echo.
pause
exit /b 1
